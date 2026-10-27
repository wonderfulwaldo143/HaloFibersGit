#!/usr/bin/env python3
"""Execute real PHP entrypoints against an isolated, capability-free Stripe fixture.
No production config/SDK is copied. No provider HTTP requests or real sessions.
"""
import concurrent.futures
import copy
import http.client
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time

ROOT = Path(__file__).resolve().parents[1]
SCRATCH = Path(os.environ.get('TMPDIR', str(Path.home() / '.hermes/cache/scratch')))
DISABLED = ','.join(['curl_init', 'curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen',
    'stream_socket_client', 'stream_socket_server', 'socket_create', 'socket_connect',
    'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'mail'])
checks = 0

def check(condition, label):
    global checks
    checks += 1
    if not condition:
        raise AssertionError(label)

with tempfile.TemporaryDirectory(prefix='hf-backend-', dir=SCRATCH) as temporary:
    work = Path(temporary)
    site = work / 'site'
    stripe = site / 'stripe'
    stripe.mkdir(parents=True)
    for source in (ROOT / 'stripe').glob('*.php'):
        if source.name != 'config.php':
            shutil.copy2(source, stripe / source.name)
    (stripe / 'stripe-php').mkdir()
    shutil.copy2(ROOT / 'tests/backend-fixture-sdk.php', stripe / 'stripe-php/init.php')
    (stripe / 'config.php').write_text("<?php define('DOMAIN','https://halofibers.com'); define('CURRENCY','usd'); define('STRIPE_SECRET_KEY','synthetic-not-a-provider-key');")
    state_path = work / 'fixtures.json'
    state_path.write_text(json.dumps({'creates': 0, 'retrieves': 0, 'line_reads': 0, 'sessions': {}}))
    router = work / 'router.php'
    router.write_text("<?php if (!is_file(__DIR__.'/no-https')) { $_SERVER['HTTPS']='on'; } if (is_file(__DIR__.'/unsafe-document-root')) { $_SERVER['DOCUMENT_ROOT']=__DIR__; } return false;")
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    log_path = work / 'server.log'
    log = log_path.open('w+')
    process = subprocess.Popen(['php', '-d', 'opcache.enable=0', '-d', 'opcache.enable_cli=0', '-d', 'allow_url_fopen=0', '-d', 'allow_url_include=0',
        '-d', 'disable_functions=' + DISABLED, '-d', 'display_errors=0', '-d', 'log_errors=1',
        '-S', f'127.0.0.1:{port}', '-t', str(site), str(router)], stdout=log, stderr=log)
    try:
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.2):
                    break
            except OSError:
                time.sleep(.03)
        else:
            raise RuntimeError('PHP fixture server failed to start')

        def request(path, method='GET', data=None, cookie=None, extra=None, raw=None):
            body = raw if raw is not None else (json.dumps(data).encode() if data is not None else None)
            headers = {'Host': 'halofibers.com', 'Origin': 'https://halofibers.com'}
            if body is not None:
                headers['Content-Type'] = 'application/json'
            if cookie:
                headers['Cookie'] = cookie
            if extra:
                for key, value in extra.items():
                    if value is None:
                        headers.pop(key, None)
                    else:
                        headers[key] = value
            connection = http.client.HTTPConnection('127.0.0.1', port, timeout=10)
            connection.request(method, path, body, headers)
            response = connection.getresponse()
            payload = response.read().decode()
            result = (response.status, dict(response.getheaders()), json.loads(payload))
            connection.close()
            check('__PRIVATE_SENTINEL__' not in payload and '/private/' not in payload and 'synthetic-not-a-provider-key' not in payload, 'response contains no private diagnostics')
            return result

        valid = {'items': [{'sku': 'hf-starter', 'shade': 'black', 'quantity': 1}], 'analytics_consent': True}
        status, _, payload = request('/stripe/catalog.php')
        check(status == 200 and payload['products']['hf-starter']['unit_amount'] == 2995, 'owner-approved release Starter USD2995 cents')
        (stripe / 'catalog-config.php').write_text("<?php return ['starter_unit_amount'=>null];")
        status, _, payload = request('/stripe/catalog.php')
        check(status == 503 and payload['error'] == 'catalog_unavailable', 'real pending catalog fail closed')
        status, _, _ = request('/stripe/create-checkout.php', 'POST', valid)
        check(status == 503 and json.loads(state_path.read_text())['creates'] == 0, 'pending checkout zero provider creates')
        # Test-only synthetic price. The application release file remains untouched.
        (stripe / 'catalog-config.php').write_text("<?php return ['starter_unit_amount'=>1234];")
        status, headers, payload = request('/stripe/catalog.php')
        check(status == 200 and payload['products']['hf-starter']['unit_amount'] == 1234, 'real catalog endpoint integer cents')
        check(headers.get('Cache-Control') == 'no-store, private' and 'Access-Control-Allow-Origin' not in headers, 'private no CORS responses')
        (work / 'no-https').touch()
        status, _, payload = request('/stripe/create-checkout.php', 'POST', valid)
        check(status == 403 and payload['error'] == 'https_required', 'cleartext transport rejected')
        (work / 'no-https').unlink()
        (work / 'unsafe-document-root').touch()
        status, _, payload = request('/stripe/create-checkout.php', 'POST', valid)
        check(status == 503 and not (work / '.halofibers-checkout').exists(), 'unsafe nested document root denied BEFORE state creation')
        (work / 'unsafe-document-root').unlink()
        (stripe / 'config.php').rename(stripe / 'config.private-fixture')
        status, _, payload = request('/stripe/create-checkout.php', 'POST', valid)
        check(status == 503 and payload == {'success': False, 'error': 'payment_unavailable'}, 'missing private configuration generic failure')
        (stripe / 'config.private-fixture').rename(stripe / 'config.php')
        attacks = []
        for bad_quantity in [1.0, 1.5, '1', -1, 0, 11, 922337203685477580799999999999999999999999]:
            bad = copy.deepcopy(valid); bad['items'][0]['quantity'] = bad_quantity
            attacks.append((bad, {}))
        for key, value in [('price', .01), ('currency', 'eur'), ('name', 'Free'), ('sku', 'unknown'), ('shade', 'Unknown')]:
            bad = copy.deepcopy(valid); bad['items'][0][key] = value
            attacks.append((bad, {}))
        attacks.append(({'items': [{'name': 'Starter Bundle', 'price': .01, 'quantity': 1, 'shade': 'black'}]}, {}))
        for headers_bad in [{'Origin': 'https://evil.test'}, {'Origin': None}, {'Origin': 'null'},
                {'Host': 'evil.test'}, {'Content-Type': 'text/plain'}, {'Sec-Fetch-Site': 'cross-site'}]:
            attacks.append((valid, headers_bad))
        for body, attack_headers in attacks:
            status, _, payload = request('/stripe/create-checkout.php', 'POST', body, extra=attack_headers)
            check(status in [400, 403, 415], 'HTTP attack rejected')
        for raw in [b'{', b'[]', b'{"items":{}}', b' ' * 16385]:
            status, _, _ = request('/stripe/create-checkout.php', 'POST', raw=raw)
            check(status in [400, 413], 'HTTP raw bounds')
        check(json.loads(state_path.read_text())['creates'] == 0, 'all rejected requests zero provider creation')
        status, _, payload = request('/stripe/verify-session.php?session_id=' + 'x' * 600)
        check(status == 413 and payload == {'verified': False}, 'receipt query byte bound')
        status, _, _ = request('/stripe/create-checkout.php')
        check(status == 405, 'real GET create method rejected')
        dupe = copy.deepcopy(valid); dupe['items'].append(copy.deepcopy(dupe['items'][0]))
        status, headers, created = request('/stripe/create-checkout.php', 'POST', dupe)
        check(status == 200 and created['success'] is True, 'real create endpoint')
        cookie_header = headers['Set-Cookie']
        check(cookie_header.startswith('__Host-hf_checkout=') and 'secure' in cookie_header.lower() and 'httponly' in cookie_header.lower() and 'SameSite=Lax' in cookie_header and 'path=/' in cookie_header and 'domain=' not in cookie_header.lower(), 'secure host-only cookie')
        cookie = cookie_header.split(';')[0]
        sid = created['sessionId']; path = '/stripe/verify-session.php?session_id=' + sid
        state = json.loads(state_path.read_text())
        check(len(state['sessions'][sid]['parameters']['line_items']) == 1 and state['sessions'][sid]['parameters']['line_items'][0]['quantity'] == 2, 'HTTP duplicate merge actual provider parameters')
        before = state['retrieves']
        status, _, payload = request(path)
        check(status == 200 and payload == {'verified': False}, 'cross-user verification denied')
        status, _, payload = request('/stripe/verify-session.php?session_id=cs_test_UnknownFixture12345', cookie=cookie)
        check(payload == {'verified': False} and json.loads(state_path.read_text())['retrieves'] == before, 'unknown sessions never retrieved')
        for badpath in ['/stripe/verify-session.php', '/stripe/verify-session.php?session_id[]=x', '/stripe/verify-session.php?session_id=bad', path + '&analytics_consent=true']:
            status, _, payload = request(badpath, cookie=cookie)
            check(payload == {'verified': False}, 'receipt query contract')
        status, _, payload = request(path, cookie=cookie, extra={'Origin': 'https://evil.test'})
        check(status == 403 and payload == {'verified': False}, 'receipt cross origin denied')
        # Unpaid/amount/identity failures must not consume the first analytics claim.
        state = json.loads(state_path.read_text()); pristine = copy.deepcopy(state['sessions'][sid])
        for key, value in [('payment_status', 'unpaid'), ('status', 'open'), ('currency', 'eur'), ('amount_total', 1)]:
            state = json.loads(state_path.read_text()); state['sessions'][sid] = copy.deepcopy(pristine)
            state['sessions'][sid]['session'][key] = value; state_path.write_text(json.dumps(state))
            status, _, payload = request(path, cookie=cookie)
            check(payload == {'verified': False}, 'HTTP unpaid/mismatch proof')
        state = json.loads(state_path.read_text()); state['sessions'][sid] = pristine; state_path.write_text(json.dumps(state))
        status, _, payload = request(path, cookie=cookie)
        check(status == 200 and payload['verified'] is True and payload['ecommerce']['value_cents'] == 2468, 'first paid receipt actual PHP')
        check(payload['ecommerce']['items'][0]['price_cents'] == 1234 and 'email' not in json.dumps(payload), 'minimized receipt')
        status, _, payload = request(path, cookie=cookie)
        check(payload == {'verified': True}, 'durable HTTP replay suppresses ecommerce')
        # Different browser sessions share durable purchase claim, not user state.
        private = work / '.halofibers-checkout'
        check(private.is_dir() and not str(private).startswith(str(site) + '/'), 'state outside webroot')
        check(all((p.stat().st_mode & 0o077) == 0 for p in private.rglob('*')), 'private file and directory modes')
        state_text = ''.join(p.read_text(errors='replace') for p in private.rglob('*') if p.is_file())
        check('synthetic-not-a-provider-key' not in state_text and '@' not in state_text and 'customer_email' not in state_text, 'state has no credentials or customer PII')
        no_consent = copy.deepcopy(valid); no_consent['analytics_consent'] = False
        status, h, created2 = request('/stripe/create-checkout.php', 'POST', no_consent)
        cookie2 = h['Set-Cookie'].split(';')[0]
        status, _, payload = request('/stripe/verify-session.php?session_id=' + created2['sessionId'], cookie=cookie2)
        check(payload == {'verified': True}, 'checkout consent false suppresses ecommerce')
        # Provider error is generic and not logged with private detail.
        state = json.loads(state_path.read_text()); state['throw'] = True; state_path.write_text(json.dumps(state))
        status, _, payload = request('/stripe/create-checkout.php', 'POST', valid)
        check(status == 503 and payload == {'success': False, 'error': 'payment_unavailable'}, 'provider exceptions sanitized')
        status, _, payload = request('/stripe/test.php')
        check(status == 404 and payload == {'error': 'not_found'}, 'legacy public diagnostics removed')
        # Raw duplicate singleton request headers, both orderings.
        for name, first, second in [('Origin','https://halofibers.com','https://evil.test'), ('Origin','https://evil.test','https://halofibers.com'), ('Host','halofibers.com','evil.test'), ('Host','evil.test','halofibers.com')]:
            lines = ['POST /stripe/create-checkout.php HTTP/1.1', 'Connection: close', 'Content-Type: application/json']
            lines += ['Host: halofibers.com'] if name != 'Host' else []
            lines += ['Origin: https://halofibers.com'] if name != 'Origin' else []
            lines += [name + ': ' + first, name + ': ' + second, 'Content-Length: ' + str(len(json.dumps(valid).encode()))]
            wire = ('\r\n'.join(lines) + '\r\n\r\n').encode() + json.dumps(valid).encode()
            with socket.create_connection(('127.0.0.1',port)) as sock:
                sock.sendall(wire); response = b''
                while True:
                    part = sock.recv(65536)
                    if not part: break
                    response += part
            code = int(response.split(b' ',2)[1])
            check(code in [400,403], 'raw duplicate header rejected: ' + name)
        # Independent real PHP processes race on the same durable claim file.
        race = work / 'race'; race.mkdir(mode=0o700)
        claim = "umask(0077); require $argv[1]; echo hf_claim_purchase($argv[2], 'cs_test_RaceFixture12345') ? '1' : '0';"
        def run_claim(_):
            return subprocess.check_output(['php','-d','allow_url_fopen=0','-r',claim,str(ROOT/'stripe/checkout-core.php'),str(race)],text=True)
        with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
            outcomes = list(pool.map(run_claim,range(16)))
        check(outcomes.count('1') == 1 and outcomes.count('0') == 15, 'cross-process atomic durable dedupe')
        log.flush(); diagnostics = log_path.read_text()
        check(not any(text in diagnostics for text in ['PHP Warning', 'PHP Fatal', 'PHP Deprecated', '__PRIVATE_SENTINEL__']), 'PHP process logs free of warnings/private errors')
        print(json.dumps({'suite':'backend-http','assertions':checks,'result':'PASS','provider_network_requests':0,'concurrent_claim_winners':outcomes.count('1')}))
    finally:
        process.terminate()
        process.wait(timeout=10)
        log.close()
