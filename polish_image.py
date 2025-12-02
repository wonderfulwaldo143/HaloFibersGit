#!/usr/bin/env python3
"""
Polish the transparent product bottle image by removing grey areas and cleaning up the bottom.
"""
from PIL import Image

def polish_transparent_image(input_path, output_path):
    """
    Clean up the transparent image by removing grey shadows and artifacts at the bottom.
    """
    # Open the image
    img = Image.open(input_path).convert("RGBA")
    width, height = img.size
    
    # Get pixel data
    pixels = img.load()
    
    # Create new image for output
    new_img = Image.new("RGBA", (width, height))
    new_pixels = new_img.load()
    
    # Process each pixel
    for y in range(height):
        for x in range(width):
            r, g, b, a = pixels[x, y]
            
            # If pixel is already transparent, keep it
            if a == 0:
                new_pixels[x, y] = (0, 0, 0, 0)
                continue
            
            # Calculate brightness and color variance
            brightness = (r + g + b) / 3
            max_color = max(r, g, b)
            min_color = min(r, g, b)
            color_variance = max_color - min_color
            
            # Focus on bottom portion (lower 30% of image) for more aggressive cleaning
            is_bottom_portion = y > height * 0.7
            
            # Remove grey/light pixels (low color variance + high brightness = grey)
            # More aggressive in bottom portion
            if is_bottom_portion:
                # In bottom portion, remove grey shadows more aggressively
                # Remove grey pixels (low variance, medium-high brightness)
                if color_variance < 30 and brightness > 100:
                    # Make transparent
                    new_pixels[x, y] = (0, 0, 0, 0)
                # Also remove very light grey pixels
                elif brightness > 200 and color_variance < 50:
                    new_pixels[x, y] = (0, 0, 0, 0)
                # Remove light grey shadows (common in bottom area)
                elif brightness > 150 and color_variance < 25 and a < 255:
                    new_pixels[x, y] = (0, 0, 0, 0)
                else:
                    # Keep the original pixel
                    new_pixels[x, y] = (r, g, b, a)
            else:
                # In upper portion, be more conservative - only remove obvious background
                if color_variance < 20 and brightness > 220:
                    new_pixels[x, y] = (0, 0, 0, 0)
                else:
                    # Keep the original pixel
                    new_pixels[x, y] = (r, g, b, a)
    
    # Additional pass: clean up edge artifacts in bottom area
    # Look for semi-transparent grey pixels that should be fully transparent
    for y in range(int(height * 0.65), height):
        for x in range(width):
            r, g, b, a = new_pixels[x, y]
            if a > 0 and a < 255:  # Semi-transparent
                brightness = (r + g + b) / 3
                max_color = max(r, g, b)
                min_color = min(r, g, b)
                color_variance = max_color - min_color
                # If it's grey and semi-transparent, make it fully transparent
                if color_variance < 40 and brightness > 120:
                    new_pixels[x, y] = (0, 0, 0, 0)
    
    # Save the polished image
    new_img.save(output_path, "PNG", optimize=True)
    print(f"Polished image saved to: {output_path}")
    print(f"Image size: {width}x{height}")

if __name__ == "__main__":
    input_file = "images/product-bottle-transparent.png"
    output_file = "images/product-bottle-polished.png"
    
    polish_transparent_image(input_file, output_file)
