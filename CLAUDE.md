# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a single-page static landing page for Halo Fibers, a hair building fibers product. The entire website is contained in a single HTML file with inline styles and JavaScript.

## Development Workflow

### Viewing Changes
- Open `index.html` directly in a web browser
- No build process or local server required
- Refresh the browser after making changes

### Git Operations
```bash
cd HaloFibersGit
git status
git add .
git commit -m "description"
git push origin main
```

## Architecture

### Technology Stack
- **HTML/CSS/JavaScript**: Vanilla implementation with no frameworks
- **Tailwind CSS**: Loaded via CDN (`https://cdn.tailwindcss.com`)
- **Icons**: Lucide icons via CDN (`https://unpkg.com/lucide@latest`)
- **Fonts**: Google Fonts (Inter + Playfair Display)

### File Structure
```
HaloFibersGit/
├── index.html          # Single-page application (all code here)
├── images/             # Product images and assets
│   ├── hero-bg.jpg
│   ├── beforehalo.jpg
│   ├── afterhalo.jpg
│   └── *.svg (logos/badges)
└── README.md
```

### Page Sections (in order)
1. **Header** - Sticky navigation with dark theme
2. **Hero** - Full-bleed background image with CTA
3. **Trusted By** - Customer reviews grid
4. **Features** - Icon grid highlighting product benefits
5. **Real Results** - Before/after slider comparison
6. **How It Works** - 3-step process
7. **Product Details** - Product description with shade selector
8. **Why Halo Fibers Win** - Comparison with competitors
9. **Offers** - Bundle pricing cards (Starter, Ultimate, Pro)
10. **FAQ** - Accordion-style questions
11. **Footer** - Brand information
12. **Mobile Sticky CTA** - Bottom sticky bar (mobile only)

### Key JavaScript Functions

**Slider Functionality**
- `initSlider(id)` - Initializes before/after image slider
- Supports both mouse and touch events
- Currently used for `beforeAfterResults` slider

**Cart Functionality**
- `addToCart(name, price)` - Handles "Add to Cart" button clicks
- Provides visual feedback (changes button to green "Added! ✓")

**Sticky CTA**
- Uses IntersectionObserver to show/hide mobile CTA when header is out of view

### Color Scheme
```javascript
brand-dark: '#0f172a'  // Slate 900 - Dark backgrounds
brand-gold: '#D4A520'  // Gold - Primary CTA and accents
brand-light: '#ffffff' // White - Text on dark
brand-gray: '#F3F4F6'  // Light Gray - Backgrounds
```

### Custom Fonts
- **Sans-serif**: Inter (body text, UI elements)
- **Serif**: Playfair Display (headings, emphasis)

## Common Modifications

### Updating Product Bundles
Edit the `#offers` section (line ~643). Each bundle card contains:
- Title and pricing
- Discount badge
- Feature list with checkmarks
- CTA button with `onclick="addToCart('name', price)"`

### Modifying Before/After Images
Replace images in `images/` directory:
- `beforehalo.jpg` - Before state
- `afterhalo.jpg` - After state

### Adjusting Color Scheme
Edit Tailwind config in `<script>` tag (line ~21):
```javascript
tailwind.config = {
  theme: {
    extend: {
      colors: {
        brand: { /* modify colors here */ }
      }
    }
  }
}
```

### Adding New Sections
- Follow existing section pattern with semantic HTML
- Use Tailwind utility classes for styling
- Keep consistent spacing: `py-24` for section padding
- Maintain light/dark alternating backgrounds
- Initialize Lucide icons with `lucide.createIcons()` if adding new icons

## Image Assets

All images are stored in `images/` directory. Key assets:
- **Hero background**: `hero-bg.jpg`
- **Before/After**: `beforehalo.jpg`, `afterhalo.jpg`
- **Media logos**: `media-*.svg` files
- **Badge icons**: `*.svg` files

## Browser Compatibility

The site uses modern web APIs:
- IntersectionObserver (for sticky CTA)
- Touch events (for mobile slider)
- CSS Grid and Flexbox
- Custom properties via Tailwind

## Performance Considerations

- All external resources loaded via CDN
- Images are not optimized/lazy-loaded
- No bundling or minification
- Single HTML file keeps deployment simple
