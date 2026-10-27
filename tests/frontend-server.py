"""Local-only storefront fixtures: never calls PHP, Stripe or external services.
Run: python3 tests/frontend-server.py --port 8767
Starter 1234 cents is a TEST FIXTURE, not a proposed owner price.
"""
import argparse
import json
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlparse, parse_qs

ROOT = Path(__file__).resolve().parents[1]
SHADES = ['black', 'dark-brown', 'medium-brown', 'light-brown', 'auburn', 'medium-blonde', 'light-blonde', 'gray', 'white']
CATALOG = {'currency': 'USD', 'products': {
    'hf-starter': {'sku': 'hf-starter', 'name': 'Starter Bundle', 'unit_amount': 1234, 'contents': ['1x Halo Fibers Bottle', '1x Pump Applicator']},
    'hf-ultimate': {'sku': 'hf-ultimate', 'name': 'Ultimate Bundle', 'unit_amount': 6995, 'contents': ['3x Halo Fibers Bottles', 'Premium Glass Applicator']},
    'hf-pro': {'sku': 'hf-pro', 'name': 'Pro Bundle', 'unit_amount': 4495, 'contents': ['2x Halo Fibers Bottles', '1x Pump Applicator']},
}, 'shades': {s: s.replace('-', ' ').title() for s in SHADES}}
POSTS = []

class Handler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(ROOT), **kwargs)
    def end_headers(self):
        self.send_header('Content-Security-Policy', "default-src 'self' data:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; connect-src 'self'; img-src 'self' data:; font-src 'self'")
        self.send_header('Referrer-Policy', 'same-origin')
        self.send_header('Cache-Control', 'no-store')
        super().end_headers()
    def respond(self, data, status=200):
        body = json.dumps(data).encode()
        self.send_response(status)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)
    def do_GET(self):
        path = urlparse(self.path)
        if path.path == '/stripe/catalog.php':
            case = parse_qs(urlparse(self.headers.get('Referer', '')).query).get('case', ['ready'])[0]
            if case == 'unavailable': return self.respond({'error':'catalog_unavailable'},503)
            if case == 'malformed': return self.respond({'currency':'USD','products':{},'shades':{}})
            return self.respond(CATALOG)
        if path.path == '/stripe/verify-session.php':
            session = parse_qs(path.query).get('session_id',[''])[0]
            if session == 'cs_test_verified123456':
                return self.respond({'verified': True, 'ecommerce': {'transaction_id':session,'currency':'USD','value_cents':1234,'items':[{'item_id':'hf-starter','item_name':'Starter Bundle','item_variant':'black','price_cents':1234,'quantity':1}]}})
            if session == 'cs_test_repeat123456': return self.respond({'verified':True})
            return self.respond({'verified': False})
        if path.path == '/_fixture/posts': return self.respond(POSTS)
        if path.path.startswith('/stripe/'):
            return self.respond({'error':'fixture_blocked'},404)
        return super().do_GET()
    def do_POST(self):
        if self.path != '/stripe/create-checkout.php': return self.respond({'error':'fixture_blocked'},405)
        data = json.loads(self.rfile.read(int(self.headers.get('Content-Length','0'))))
        POSTS.append({'path':self.path,'body':data,'origin':self.headers.get('Origin')})
        return self.respond({'success':False,'error':'fixture_checkout_unavailable'},503)
    def log_message(self, format, *args):
        pass

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--port',type=int,default=8767)
    args = parser.parse_args()
    print(f'FRONTEND FIXTURE ONLY http://127.0.0.1:{args.port} (external requests blocked by CSP)',flush=True)
    ThreadingHTTPServer(('127.0.0.1',args.port),Handler).serve_forever()
