#!/usr/bin/env python3
"""
TravianZ - Authentic Majestic Tier 4 Hero Items Generator
Generates high-resolution (256x256) item icons that strictly adhere to
the authentic classic Travian T4 medieval browser game art style:
- Warm antique cream/beige parchment background (#f6eedb to #e8d8ba)
- Soft antique divine golden sunburst halo & ambient occlusion drop shadow
- Noble ancient Roman, Greek, Norse, and Medieval fantasy craftsmanship
- Hand-drawn illustrated aesthetic with warm burnished gold, polished steel,
  fine bronze, deep red velvet, and precious gemstones
"""

import os
import math
from PIL import Image, ImageDraw, ImageFilter, ImageEnhance

SIZE = 256
CENTER = SIZE // 2
OUTPUT_DIR = "img/hero/legendary"
os.makedirs(OUTPUT_DIR, exist_ok=True)

# ==============================================================================
# Helper: Base Canvas with Authentic Travian Warm Parchment & Subtle Halo
# ==============================================================================
def create_travian_parchment(halo_color=(255, 215, 0), halo_strength=0.25):
    """
    Creates an authentic 256x256 Travian parchment background with:
    - Warm gradient from center (#faf4e6) to edge (#ebdcb9)
    - Delicate antique paper grain
    - Subtle divine sunburst rays
    - Ambient drop shadow floor
    """
    canvas = Image.new("RGBA", (SIZE, SIZE), (248, 242, 228, 255))
    draw = ImageDraw.Draw(canvas)

    # 1. Soft radial parchment lighting
    for r in range(SIZE, 0, -2):
        t = r / SIZE
        # Warm parchment gradient
        cr = int(250 - 24 * t)
        cg = int(242 - 32 * t)
        cb = int(224 - 46 * t)
        draw.ellipse((CENTER - r, CENTER - r, CENTER + r, CENTER + r), fill=(cr, cg, cb, 255))

    # 2. Antique Divine Sunburst Rays (Warm Godrays)
    if halo_strength > 0:
        halo = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
        hdraw = ImageDraw.Draw(halo)
        num_rays = 24
        ray_len = 115
        for i in range(num_rays):
            angle = i * (2 * math.pi / num_rays)
            w = 0.08
            x1 = CENTER + math.cos(angle - w) * 20
            y1 = CENTER + math.sin(angle - w) * 20
            x2 = CENTER + math.cos(angle) * ray_len
            y2 = CENTER + math.sin(angle) * ray_len
            x3 = CENTER + math.cos(angle + w) * 20
            y3 = CENTER + math.sin(angle + w) * 20
            alpha = int(65 * halo_strength)
            hdraw.polygon([(x1, y1), (x2, y2), (x3, y3)], fill=(*halo_color, alpha))
        
        # Soft blur on sunburst
        halo = halo.filter(ImageFilter.GaussianBlur(radius=8))
        canvas = Image.alpha_composite(canvas, halo)

    # 3. Ambient Drop Shadow underneath the item position
    shadow = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    sdraw = ImageDraw.Draw(shadow)
    # Ground shadow ellipse
    sdraw.ellipse((CENTER - 60, CENTER + 65, CENTER + 60, CENTER + 95), fill=(80, 60, 40, 95))
    shadow = shadow.filter(ImageFilter.GaussianBlur(radius=10))
    canvas = Image.alpha_composite(canvas, shadow)

    return canvas

def add_antique_gold_frame(canvas):
    """
    Adds a refined, classical antique gold border with Roman imperial corner flourishes.
    """
    frame = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(frame)

    gold_dark  = (140, 95, 30, 240)
    gold_mid   = (218, 165, 32, 255)
    gold_light = (255, 225, 120, 255)
    ruby_red   = (190, 25, 25, 255)

    # Outer fine borders
    draw.rectangle((4, 4, SIZE - 5, SIZE - 5), outline=gold_dark, width=2)
    draw.rectangle((7, 7, SIZE - 8, SIZE - 8), outline=gold_mid, width=1)
    draw.rectangle((10, 10, SIZE - 11, SIZE - 11), outline=(180, 140, 70, 120), width=1)

    # 4 Corner Rosettes / Jewels
    corners = [(9, 9), (SIZE - 10, 9), (9, SIZE - 10), (SIZE - 10, SIZE - 10)]
    for cx, cy in corners:
        draw.ellipse((cx - 5, cy - 5, cx + 5, cy + 5), fill=gold_dark, outline=gold_light, width=1)
        draw.ellipse((cx - 3, cy - 3, cx + 3, cy + 3), fill=ruby_red)
        draw.ellipse((cx - 1, cy - 1, cx + 1, cy + 1), fill=(255, 200, 200, 255))

    return Image.alpha_composite(canvas, frame)

# ==============================================================================
# 210: World Essence (Mustika Surya Kuno / Sacred Sunstone Relic)
# ==============================================================================
def draw_item_210():
    canvas = create_travian_parchment(halo_color=(255, 200, 50), halo_strength=0.35)
    fg = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)

    # Ancient Sunstone Amber Orb
    r = 44
    # Ambient soft glow around gem
    glow = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    gd = ImageDraw.Draw(glow)
    gd.ellipse((CENTER - 60, CENTER - 60, CENTER + 60, CENTER + 60), fill=(255, 170, 0, 140))
    glow = glow.filter(ImageFilter.GaussianBlur(16))
    canvas = Image.alpha_composite(canvas, glow)

    # Outer ornate bronze/gold sunburst ring
    for deg in range(0, 360, 30):
        rad = math.radians(deg)
        x1 = CENTER + math.cos(rad) * (r + 4)
        y1 = CENTER + math.sin(rad) * (r + 4)
        x2 = CENTER + math.cos(rad) * (r + 22)
        y2 = CENTER + math.sin(rad) * (r + 22)
        # Flame spikes
        draw.polygon([
            (CENTER + math.cos(rad - 0.12) * (r + 5), CENTER + math.sin(rad - 0.12) * (r + 5)),
            (x2, y2),
            (CENTER + math.cos(rad + 0.12) * (r + 5), CENTER + math.sin(rad + 0.12) * (r + 5))
        ], fill=(218, 165, 32, 255), outline=(139, 69, 19, 255))

    # Heavy gold mount rim
    draw.ellipse((CENTER - r - 6, CENTER - r - 6, CENTER + r + 6, CENTER + r + 6), fill=(160, 110, 30, 255), outline=(80, 50, 15, 255), width=2)
    draw.ellipse((CENTER - r - 3, CENTER - r - 3, CENTER + r + 3, CENTER + r + 3), fill=(218, 165, 32, 255), outline=(255, 230, 140, 255), width=2)

    # Inner Amber Crystal Layers
    for i in range(r, 0, -1):
        factor = i / r
        # Deep amber to radiant bright gold
        cr = int(255 - 15 * factor)
        cg = int(220 - 90 * factor)
        cb = int(30 + 10 * factor)
        draw.ellipse((CENTER - i, CENTER - i, CENTER + i, CENTER + i), fill=(cr, cg, cb, 255))

    # Internal Celtic/Runic Sun spiral
    for deg in range(0, 720, 20):
        rad = math.radians(deg)
        dist = (deg / 720) * (r - 10)
        sx = CENTER + math.cos(rad) * dist
        sy = CENTER + math.sin(rad) * dist
        draw.ellipse((sx - 2, sy - 2, sx + 2, sy + 2), fill=(255, 255, 180, 220))

    # Shiny crystal specular highlight
    draw.ellipse((CENTER - 26, CENTER - 28, CENTER - 6, CENTER - 12), fill=(255, 255, 230, 230))
    draw.ellipse((CENTER - 20, CENTER - 22, CENTER - 12, CENTER - 16), fill=(255, 255, 255, 255))

    canvas = Image.alpha_composite(canvas, fg)
    return add_antique_gold_frame(canvas)

# ==============================================================================
# 401: Crown of the Eternal Pantheon (Mahkota Kaisar Mahadewa)
# ==============================================================================
def draw_item_401():
    # If the high-res AI-generated image exists, composite and frame it
    ai_path = "/Users/codeemperor/.gemini/antigravity/brain/f6772e82-f14b-4368-aad8-d3219b3cff59/crown_eternal_pantheon_1794272890609.jpg"
    if os.path.exists(ai_path):
        raw = Image.open(ai_path).convert("RGBA")
        # Crop inner area to remove any unwanted border and resize
        w, h = raw.size
        crop_box = (int(w * 0.05), int(h * 0.05), int(w * 0.95), int(h * 0.95))
        cropped = raw.crop(crop_box).resize((SIZE, SIZE), Image.Resampling.LANCZOS)
        # Enhance contrast slightly to pop on Travian UI
        enhancer = ImageEnhance.Color(cropped)
        cropped = enhancer.enhance(1.15)
        return add_antique_gold_frame(cropped)

    # Fallback painterly imperial crown
    canvas = create_travian_parchment(halo_color=(255, 215, 0), halo_strength=0.3)
    fg = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)

    # Golden laurel wreath base
    for side in [-1, 1]:
        for i in range(7):
            ang = 0.5 + i * 0.35
            lx = CENTER + side * (math.cos(ang) * 45 + 15)
            ly = CENTER + 40 - math.sin(ang) * 55
            draw.ellipse((lx - 12, ly - 6, lx + 12, ly + 6), fill=(218, 165, 32, 255), outline=(139, 69, 19, 255), width=2)
            draw.ellipse((lx - 8, ly - 3, lx + 8, ly + 3), fill=(255, 225, 100, 255))

    # Imperial Roman Diadem Crown
    draw.rectangle((CENTER - 60, CENTER + 15, CENTER + 60, CENTER + 42), fill=(218, 165, 32, 255), outline=(120, 80, 20, 255), width=2)
    # Crown spikes
    spikes = [(-50, -10), (-25, -30), (0, -45), (25, -30), (50, -10)]
    for x, y in spikes:
        draw.polygon([(CENTER + x - 12, CENTER + 15), (CENTER + x, CENTER + y), (CENTER + x + 12, CENTER + 15)],
                     fill=(245, 195, 45, 255), outline=(139, 69, 19, 255), width=2)
        # Ruby tops
        draw.ellipse((CENTER + x - 6, CENTER + y - 6, CENTER + x + 6, CENTER + y + 6), fill=(200, 20, 30, 255), outline=(255, 220, 100, 255), width=1)

    canvas = Image.alpha_composite(canvas, fg)
    return add_antique_gold_frame(canvas)

# ==============================================================================
# 402: War-Crown of Supreme Command (Helm Perang Kaisar Berjambul Merah)
# ==============================================================================
def draw_item_402():
    canvas = create_travian_parchment(halo_color=(255, 190, 40), halo_strength=0.25)
    fg = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)

    # 1. Majestic Roman Crimson Horsehair Crest Plume
    # Arc of red crest
    crest_pts = []
    for deg in range(120, 240, 4):
        rad = math.radians(deg)
        cx = CENTER + math.cos(rad) * 75
        cy = CENTER - 10 + math.sin(rad) * 60
        crest_pts.append((cx, cy))
    
    # Plume bristles
    for pt in crest_pts:
        draw.line([pt, (pt[0], pt[1] - 28)], fill=(185, 25, 25, 255), width=4)
        draw.line([pt, (pt[0], pt[1] - 24)], fill=(230, 45, 45, 255), width=2)

    # Crest Brass Holder Arc
    for deg in range(120, 240, 5):
        rad = math.radians(deg)
        bx = CENTER + math.cos(rad) * 75
        by = CENTER - 10 + math.sin(rad) * 60
        draw.ellipse((bx - 4, by - 4, bx + 4, by + 4), fill=(218, 165, 32, 255), outline=(120, 80, 20, 255))

    # 2. Main Steel Helm Bowl
    draw.ellipse((CENTER - 46, CENTER - 20, CENTER + 46, CENTER + 48), fill=(160, 170, 180, 255), outline=(60, 65, 75, 255), width=3)
    # Highlight on steel
    draw.ellipse((CENTER - 38, CENTER - 15, CENTER + 10, CENTER + 25), fill=(205, 215, 225, 255))

    # 3. Heavy Gold Browband & Emperor Lion Emblem
    draw.rectangle((CENTER - 45, CENTER + 10, CENTER + 45, CENTER + 26), fill=(218, 165, 32, 255), outline=(139, 69, 19, 255), width=2)
    # Lion Face Medallion
    draw.ellipse((CENTER - 14, CENTER + 6, CENTER + 14, CENTER + 30), fill=(245, 200, 50, 255), outline=(120, 70, 15, 255), width=2)
    draw.ellipse((CENTER - 5, CENTER + 14, CENTER + 5, CENTER + 24), fill=(160, 90, 20, 255))

    # 4. Cheek Guards (Pelindung Pipi)
    for side in [-1, 1]:
        draw.polygon([
            (CENTER + side * 42, CENTER + 25),
            (CENTER + side * 44, CENTER + 65),
            (CENTER + side * 24, CENTER + 68),
            (CENTER + side * 22, CENTER + 25)
        ], fill=(175, 185, 195, 255), outline=(80, 85, 95, 255), width=2)
        # Gold boss on cheek guard
        draw.ellipse((CENTER + side * 33 - 5, CENTER + 42 - 5, CENTER + side * 33 + 5, CENTER + 42 + 5), fill=(218, 165, 32, 255))

    canvas = Image.alpha_composite(canvas, fg)
    return add_antique_gold_frame(canvas)

# ==============================================================================
# 403: Visage of the Undying Phoenix (Helm Bersayap Phoenix Emas)
# ==============================================================================
def draw_item_403():
    canvas = create_travian_parchment(halo_color=(255, 170, 40), halo_strength=0.3)
    fg = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)

    # 1. Divine Phoenix Golden Wings on left and right
    for side in [-1, 1]:
        wing_feathers = [
            (35, -55, 75, -80),
            (42, -40, 85, -60),
            (46, -25, 90, -40),
            (48, -10, 88, -20),
            (45, 5, 80, -5)
        ]
        for x1, y1, x2, y2 in wing_feathers:
            px1, py1 = CENTER + side * x1, CENTER + y1
            px2, py2 = CENTER + side * x2, CENTER + y2
            draw.polygon([
                (px1, py1 - 8), (px2, py2), (px1, py1 + 8)
            ], fill=(235, 185, 45, 255), outline=(139, 69, 19, 255), width=2)
            draw.polygon([
                (px1, py1 - 4), (px2 - side * 6, py2 + 2), (px1, py1 + 4)
            ], fill=(255, 235, 120, 255))

    # 2. Main Gold Helmet Dome
    draw.ellipse((CENTER - 45, CENTER - 35, CENTER + 45, CENTER + 35), fill=(218, 165, 32, 255), outline=(120, 75, 15, 255), width=3)
    draw.ellipse((CENTER - 35, CENTER - 28, CENTER + 15, CENTER + 15), fill=(255, 225, 100, 255))

    # 3. Phoenix Visor & Beak
    draw.polygon([
        (CENTER - 20, CENTER - 5),
        (CENTER, CENTER + 38),
        (CENTER + 20, CENTER - 5),
        (CENTER, CENTER + 10)
    ], fill=(245, 195, 45, 255), outline=(139, 69, 19, 255), width=2)

    # Glowing Ruby Phoenix Eyes
    for side in [-1, 1]:
        draw.ellipse((CENTER + side * 14 - 6, CENTER + 5 - 4, CENTER + side * 14 + 6, CENTER + 5 + 4),
                     fill=(210, 25, 35, 255), outline=(255, 220, 120, 255), width=1)
        draw.ellipse((CENTER + side * 14 - 2, CENTER + 5 - 2, CENTER + side * 14 + 2, CENTER + 5 + 2), fill=(255, 255, 255, 255))

    canvas = Image.alpha_composite(canvas, fg)
    return add_antique_gold_frame(canvas)

# ==============================================================================
# 404: Aegis Plate of the World-Eater (Zirah Lempeng Naga Kaisar)
# ==============================================================================
def draw_item_404():
    canvas = create_travian_parchment(halo_color=(255, 180, 50), halo_strength=0.25)
    fg = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)

    # 1. Massive Triple-Tiered Pauldrons (Pundak Zirah)
    for side in [-1, 1]:
        for tier in range(3):
            py = CENTER - 35 + tier * 16
            px = CENTER + side * (50 + tier * 8)
            draw.ellipse((px - 26, py - 14, px + 26, py + 14), fill=(75, 80, 90, 255), outline=(218, 165, 32, 255), width=3)
            draw.ellipse((px - 18, py - 8, px + 18, py + 8), fill=(105, 115, 125, 255))

    # 2. Main Cuirass Torso (Baja Hitam Bertatah Emas)
    draw.polygon([
        (CENTER - 45, CENTER - 40),
        (CENTER + 45, CENTER - 40),
        (CENTER + 40, CENTER + 45),
        (CENTER, CENTER + 68),
        (CENTER - 40, CENTER + 45)
    ], fill=(60, 65, 75, 255), outline=(139, 69, 19, 255), width=3)

    # Segmented Lorica Lines & Gold Borders
    for y in range(CENTER - 20, CENTER + 45, 16):
        draw.line([(CENTER - 36, y), (CENTER + 36, y)], fill=(218, 165, 32, 255), width=3)

    # Golden Imperial Dragon/Lion Breastplate Emblem
    draw.ellipse((CENTER - 22, CENTER - 18, CENTER + 22, CENTER + 18), fill=(235, 185, 45, 255), outline=(139, 69, 19, 255), width=2)
    draw.ellipse((CENTER - 8, CENTER - 6, CENTER + 8, CENTER + 6), fill=(185, 25, 25, 255))

    # Pteruges (Rumbai Kulit Bawah Zirah)
    for x in range(-32, 33, 14):
        draw.rectangle((CENTER + x - 5, CENTER + 55, CENTER + x + 5, CENTER + 82), fill=(139, 69, 19, 255), outline=(80, 40, 10, 255), width=1)
        draw.ellipse((CENTER + x - 3, CENTER + 73, CENTER + x + 3, CENTER + 79), fill=(218, 165, 32, 255))

    canvas = Image.alpha_composite(canvas, fg)
    return add_antique_gold_frame(canvas)

# ==============================================================================
# 405: Cuirass of the Colossus (Zirah Otot Kaisar Emas)
# ==============================================================================
def draw_item_405():
    canvas = create_travian_parchment(halo_color=(255, 215, 0), halo_strength=0.35)
    fg = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)

    # Classical Greek/Roman Heroic Muscle Cuirass
    # 1. Torso Base
    draw.polygon([
        (CENTER - 48, CENTER - 45),
        (CENTER + 48, CENTER - 45),
        (CENTER + 42, CENTER + 45),
        (CENTER, CENTER + 66),
        (CENTER - 42, CENTER + 45)
    ], fill=(218, 165, 32, 255), outline=(120, 75, 15, 255), width=3)

    # 2. Sculpted Muscle Definition
    # Pectoral Plates
    for side in [-1, 1]:
        draw.ellipse((CENTER + side * 22 - 18, CENTER - 32, CENTER + side * 22 + 18, CENTER + 2),
                     fill=(245, 195, 45, 255), outline=(160, 100, 20, 255), width=2)
        draw.ellipse((CENTER + side * 22 - 12, CENTER - 26, CENTER + side * 22 + 8, CENTER - 6),
                     fill=(255, 230, 110, 255))

    # Abdominal Muscles
    for y in [CENTER + 10, CENTER + 28, CENTER + 45]:
        draw.ellipse((CENTER - 20, y - 6, CENTER - 2, y + 6), fill=(240, 185, 40, 255), outline=(150, 90, 20, 255), width=1)
        draw.ellipse((CENTER + 2, y - 6, CENTER + 20, y + 6), fill=(240, 185, 40, 255), outline=(150, 90, 20, 255), width=1)

    # Central Sun God Medallion on sternum
    draw.ellipse((CENTER - 12, CENTER - 18, CENTER + 12, CENTER + 6), fill=(255, 235, 130, 255), outline=(139, 69, 19, 255), width=2)
    draw.ellipse((CENTER - 4, CENTER - 9, CENTER + 4, CENTER - 1), fill=(200, 20, 30, 255))

    # Leather & Gold Pteruges
    for x in range(-32, 33, 14):
        draw.rectangle((CENTER + x - 5, CENTER + 54, CENTER + x + 5, CENTER + 80), fill=(120, 55, 15, 255), outline=(70, 30, 5, 255), width=1)
        draw.ellipse((CENTER + x - 3, CENTER + 71, CENTER + x + 3, CENTER + 77), fill=(245, 205, 50, 255))

    canvas = Image.alpha_composite(canvas, fg)
    return add_antique_gold_frame(canvas)

# ==============================================================================
# 406: Sovereign's Warblade (Pedang Dua Tangan Raja Semesta)
# ==============================================================================
def draw_item_406():
    canvas = create_travian_parchment(halo_color=(255, 215, 0), halo_strength=0.3)
    fg = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)

    # Diagonal royal broadsword
    # Blade angle: from bottom-left to top-right
    bx1, by1 = CENTER - 45, CENTER + 45
    bx2, by2 = CENTER + 75, CENTER - 75

    # 1. Damascus Steel Blade
    # Blade polygon with bevel
    draw.polygon([
        (bx1 - 10, by1 + 10),
        (bx2 + 5, by2 - 5),
        (bx2 + 15, by2 - 15), # Point
        (bx2 - 5, by2 + 5),
        (bx1 + 10, by1 - 10)
    ], fill=(225, 232, 240, 255), outline=(90, 100, 115, 255), width=2)

    # Golden fuller down the center
    draw.line([(bx1, by1), (bx2 - 10, by2 + 10)], fill=(218, 165, 32, 255), width=3)
    draw.line([(bx1, by1), (bx2 - 10, by2 + 10)], fill=(255, 240, 150, 255), width=1)

    # 2. Ornate Golden Winged Crossguard
    hx, hy = bx1, by1
    draw.polygon([
        (hx - 28, hy - 14), (hx + 14, hy + 28),
        (hx + 24, hy + 18), (hx - 18, hy - 24)
    ], fill=(218, 165, 32, 255), outline=(139, 69, 19, 255), width=2)
    # Ruby in crossguard center
    draw.ellipse((hx - 6, hy - 6, hx + 6, hy + 6), fill=(200, 20, 30, 255), outline=(255, 220, 100, 255), width=1)

    # 3. Imperial Red Velvet Grip & Pommel
    gx, gy = hx - 28, hy + 28
    draw.line([(hx, hy), (gx, gy)], fill=(160, 20, 30, 255), width=7)
    draw.line([(hx, hy), (gx, gy)], fill=(218, 165, 32, 255), width=2)

    # Massive Ruby Pommel
    draw.ellipse((gx - 10, gy - 10, gx + 10, gy + 10), fill=(185, 20, 30, 255), outline=(245, 195, 45, 255), width=2)
    draw.ellipse((gx - 4, gy - 6, gx + 2, gy), fill=(255, 200, 200, 255))

    canvas = Image.alpha_composite(canvas, fg)
    return add_antique_gold_frame(canvas)

# ==============================================================================
# 407: Gungnir, The Stormbringer (Tombak Badai Pusaka Dewa)
# ==============================================================================
def draw_item_407():
    canvas = create_travian_parchment(halo_color=(255, 205, 50), halo_strength=0.3)
    fg = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)

    # Diagonal Shaft (Ash Wood with Golden Bands)
    sx1, sy1 = CENTER - 70, CENTER + 75
    sx2, sy2 = CENTER + 45, CENTER - 40
    draw.line([(sx1, sy1), (sx2, sy2)], fill=(90, 50, 20, 255), width=8)
    draw.line([(sx1, sy1), (sx2, sy2)], fill=(130, 75, 35, 255), width=4)

    # Gold bands along shaft
    for t in [0.2, 0.4, 0.6, 0.8]:
        mx = sx1 + (sx2 - sx1) * t
        my = sy1 + (sy2 - sy1) * t
        draw.ellipse((mx - 5, my - 5, mx + 5, my + 5), fill=(218, 165, 32, 255))

    # Massive Ornate Norse Gungnir Spearhead
    tip_x, tip_y = CENTER + 82, CENTER - 78
    base_x, base_y = sx2, sy2

    draw.polygon([
        (base_x - 18, base_y + 12),
        (tip_x, tip_y),
        (base_x + 12, base_y - 18),
        (base_x, base_y)
    ], fill=(230, 238, 245, 255), outline=(139, 69, 19, 255), width=2)

    # Gold winglets and lightning fuller
    draw.polygon([
        (base_x - 14, base_y + 8),
        (base_x + (tip_x - base_x) * 0.4, base_y + (tip_y - base_y) * 0.4),
        (base_x + 8, base_y - 14)
    ], fill=(218, 165, 32, 255))
    draw.line([(base_x, base_y), (tip_x - 8, tip_y + 8)], fill=(255, 240, 160, 255), width=3)

    canvas = Image.alpha_composite(canvas, fg)
    return add_antique_gold_frame(canvas)

# ==============================================================================
# 408: Worldbreaker Warhammer (Palu Godam Penghancur Benteng)
# ==============================================================================
def draw_item_408():
    canvas = create_travian_parchment(halo_color=(255, 180, 40), halo_strength=0.25)
    fg = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)

    # Sturdy Haft
    hx1, hy1 = CENTER - 55, CENTER + 75
    hx2, hy2 = CENTER + 20, CENTER - 10
    draw.line([(hx1, hy1), (hx2, hy2)], fill=(75, 45, 20, 255), width=10)
    draw.line([(hx1, hy1), (hx2, hy2)], fill=(218, 165, 32, 255), width=3)

    # Massive Forged Dual-Headed Hammer Block
    cx, cy = hx2, hy2
    draw.polygon([
        (cx - 38, cy - 22),
        (cx + 22, cy - 44),
        (cx + 44, cy - 22),
        (cx - 16, cy)
    ], fill=(95, 105, 115, 255), outline=(50, 55, 60, 255), width=3)

    # Gold Strike Faces & Engravings
    draw.polygon([(cx - 38, cy - 22), (cx - 48, cy - 12), (cx - 26, cy + 10), (cx - 16, cy)],
                 fill=(218, 165, 32, 255), outline=(139, 69, 19, 255), width=2)
    draw.polygon([(cx + 22, cy - 44), (cx + 32, cy - 54), (cx + 54, cy - 32), (cx + 44, cy - 22)],
                 fill=(218, 165, 32, 255), outline=(139, 69, 19, 255), width=2)

    # Central Dwarven Rune Core
    draw.ellipse((cx - 2, cy - 24, cx + 18, cy - 4), fill=(245, 200, 50, 255), outline=(120, 70, 15, 255), width=2)
    draw.ellipse((cx + 4, cy - 18, cx + 12, cy - 10), fill=(200, 20, 30, 255))

    canvas = Image.alpha_composite(canvas, fg)
    return add_antique_gold_frame(canvas)

# ==============================================================================
# 409: Horn of Global Dominion (Terompet Perang Gading Kaisar)
# ==============================================================================
def draw_item_409():
    canvas = create_travian_parchment(halo_color=(255, 200, 50), halo_strength=0.25)
    fg = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)

    # Ivory War Horn Curve
    # Generate smooth horn body
    points = []
    for deg in range(30, 210, 10):
        rad = math.radians(deg)
        r_curve = 70
        px = CENTER + 10 + math.cos(rad) * r_curve
        py = CENTER + 15 + math.sin(rad) * 45
        points.append((px, py))

    # Draw horn thickness
    for i in range(len(points) - 1):
        p1 = points[i]
        p2 = points[i + 1]
        w = int(8 + (i / len(points)) * 28)
        draw.line([p1, p2], fill=(245, 238, 220, 255), width=w)
        draw.line([p1, p2], fill=(139, 69, 19, 255), width=1)

    # Flared Bell (Mulut Terompet Emas)
    bell_x, bell_y = points[-1]
    draw.ellipse((bell_x - 16, bell_y - 28, bell_x + 16, bell_y + 28), fill=(218, 165, 32, 255), outline=(139, 69, 19, 255), width=2)
    draw.ellipse((bell_x - 10, bell_y - 20, bell_x + 10, bell_y + 20), fill=(120, 70, 15, 255))

    # Embossed Gold Bands & Crimson Cords
    for idx in [2, 5, 9, 13]:
        if idx < len(points):
            bx, by = points[idx]
            draw.ellipse((bx - 12, by - 12, bx + 12, by + 12), fill=(235, 185, 45, 255), outline=(139, 69, 19, 255), width=1)

    # Hanging Crimson Tassels
    draw.line([(CENTER - 20, CENTER + 45), (CENTER - 30, CENTER + 80)], fill=(185, 25, 25, 255), width=4)
    draw.ellipse((CENTER - 35, CENTER + 75, CENTER - 25, CENTER + 85), fill=(218, 165, 32, 255))

    canvas = Image.alpha_composite(canvas, fg)
    return add_antique_gold_frame(canvas)

# ==============================================================================
# 410: Astral Chrono-Sextant (Sekstan Bintang Perunggu Antik)
# ==============================================================================
def draw_item_410():
    canvas = create_travian_parchment(halo_color=(255, 215, 50), halo_strength=0.3)
    fg = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)

    # Brass Astrolabe Frame Arc
    draw.arc((CENTER - 65, CENTER - 65, CENTER + 65, CENTER + 65), start=45, end=195, fill=(180, 120, 30, 255), width=8)
    draw.arc((CENTER - 65, CENTER - 65, CENTER + 65, CENTER + 65), start=45, end=195, fill=(245, 205, 50, 255), width=3)

    # Degree Graduation Ticks
    for deg in range(50, 190, 10):
        rad = math.radians(deg)
        x1 = CENTER + math.cos(rad) * 60
        y1 = CENTER + math.sin(rad) * 60
        x2 = CENTER + math.cos(rad) * 68
        y2 = CENTER + math.sin(rad) * 68
        draw.line([(x1, y1), (x2, y2)], fill=(120, 70, 15, 255), width=2)

    # Pivoting Brass Scope Arm
    draw.line([(CENTER - 50, CENTER + 50), (CENTER + 50, CENTER - 50)], fill=(218, 165, 32, 255), width=6)
    draw.line([(CENTER - 50, CENTER + 50), (CENTER + 50, CENTER - 50)], fill=(255, 240, 150, 255), width=2)

    # Sighting Mini-Telescope
    draw.rectangle((CENTER + 20, CENTER - 55, CENTER + 55, CENTER - 35), fill=(185, 130, 35, 255), outline=(90, 50, 10, 255), width=2)

    # Central Celestial Sapphire Core
    draw.ellipse((CENTER - 16, CENTER - 16, CENTER + 16, CENTER + 16), fill=(218, 165, 32, 255), outline=(120, 70, 15, 255), width=2)
    draw.ellipse((CENTER - 10, CENTER - 10, CENTER + 10, CENTER + 10), fill=(30, 110, 210, 255), outline=(200, 230, 255, 255), width=1)
    draw.ellipse((CENTER - 4, CENTER - 6, CENTER + 2, CENTER), fill=(255, 255, 255, 255))

    canvas = Image.alpha_composite(canvas, fg)
    return add_antique_gold_frame(canvas)

# ==============================================================================
# 411: Mirror Shield of Reflection (Perisai Cermin Pemantul Serangan)
# ==============================================================================
def draw_item_411():
    canvas = create_travian_parchment(halo_color=(255, 215, 0), halo_strength=0.35)
    fg = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)

    # Majestic Round Hoplite Shield
    r_shield = 68

    # Heavy Ornate Golden Outer Rim
    draw.ellipse((CENTER - r_shield, CENTER - r_shield, CENTER + r_shield, CENTER + r_shield),
                 fill=(218, 165, 32, 255), outline=(120, 75, 15, 255), width=3)

    # Embossed Laurel Wreath around the rim
    draw.ellipse((CENTER - r_shield + 10, CENTER - r_shield + 10, CENTER + r_shield - 10, CENTER + r_shield - 10),
                 fill=(160, 100, 20, 255), outline=(245, 205, 50, 255), width=2)

    # Mirror Steel Polished Dish
    r_mirror = r_shield - 14
    for i in range(r_mirror, 0, -1):
        t = i / r_mirror
        # Polished reflective silver gradient
        c = int(185 + 65 * (1 - t))
        draw.ellipse((CENTER - i, CENTER - i, CENTER + i, CENTER + i), fill=(c, c + 5, c + 15, 255))

    # Specular Mirror Sheen / Reflection Cross
    sheen = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    sdraw = ImageDraw.Draw(sheen)
    sdraw.polygon([
        (CENTER - 35, CENTER - 45), (CENTER + 45, CENTER + 35),
        (CENTER + 35, CENTER + 45), (CENTER - 45, CENTER - 35)
    ], fill=(255, 255, 255, 110))
    sheen = sheen.filter(ImageFilter.GaussianBlur(6))
    fg = Image.alpha_composite(fg, sheen)
    draw = ImageDraw.Draw(fg)

    # Golden Central Sun Boss (Pusat Perisai Emas)
    draw.ellipse((CENTER - 18, CENTER - 18, CENTER + 18, CENTER + 18), fill=(245, 195, 45, 255), outline=(139, 69, 19, 255), width=2)
    draw.ellipse((CENTER - 8, CENTER - 8, CENTER + 8, CENTER + 8), fill=(185, 25, 25, 255))

    canvas = Image.alpha_composite(canvas, fg)
    return add_antique_gold_frame(canvas)

# ==============================================================================
# 412: Hermes' Astral Greaves (Pelindung Kaki Bersayap Emas)
# ==============================================================================
def draw_item_412():
    canvas = create_travian_parchment(halo_color=(255, 215, 50), halo_strength=0.3)
    fg = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)

    # Pair of Golden Greaves (Pelindung Tulang Kering Betis)
    for side in [-1, 1]:
        cx = CENTER + side * 28

        # Divine Wing sweeping outward
        wing_feathers = [
            (cx + side * 5, CENTER + 35, cx + side * 45, CENTER - 15),
            (cx + side * 5, CENTER + 42, cx + side * 50, CENTER + 5),
            (cx + side * 5, CENTER + 50, cx + side * 46, CENTER + 25),
        ]
        for wx1, wy1, wx2, wy2 in wing_feathers:
            draw.polygon([(wx1, wy1 - 6), (wx2, wy2), (wx1, wy1 + 6)],
                         fill=(245, 205, 50, 255), outline=(139, 69, 19, 255), width=2)
            draw.polygon([(wx1, wy1 - 2), (wx2 - side * 4, wy2 + 2), (wx1, wy1 + 2)], fill=(255, 240, 150, 255))

        # Shin Plate (Emas Berkilau Anatomis)
        draw.polygon([
            (cx - 16, CENTER - 45),
            (cx + 16, CENTER - 45),
            (cx + 14, CENTER + 45),
            (cx, CENTER + 65),
            (cx - 14, CENTER + 45)
        ], fill=(218, 165, 32, 255), outline=(120, 75, 15, 255), width=2)

        # Knee Boss
        draw.ellipse((cx - 12, CENTER - 42, cx + 12, CENTER - 18), fill=(245, 200, 50, 255), outline=(139, 69, 19, 255), width=1)
        draw.ellipse((cx - 4, CENTER - 34, cx + 4, CENTER - 26), fill=(185, 20, 20, 255))

    canvas = Image.alpha_composite(canvas, fg)
    return add_antique_gold_frame(canvas)

# ==============================================================================
# 413: Spurs of the Apocalypse (Taji Berkuda Roda Bintang Emas)
# ==============================================================================
def draw_item_413():
    canvas = create_travian_parchment(halo_color=(255, 190, 40), halo_strength=0.25)
    fg = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)

    # Heavy Golden U-Shaped Heel Yoke
    draw.arc((CENTER - 60, CENTER - 35, CENTER + 60, CENTER + 45), start=30, end=150, fill=(218, 165, 32, 255), width=14)
    draw.arc((CENTER - 60, CENTER - 35, CENTER + 60, CENTER + 45), start=30, end=150, fill=(255, 225, 100, 255), width=4)

    # Leather Straps & Buckles
    draw.line([(CENTER - 45, CENTER + 25), (CENTER - 70, CENTER - 20)], fill=(120, 55, 15, 255), width=8)
    draw.line([(CENTER + 45, CENTER + 25), (CENTER + 70, CENTER - 20)], fill=(120, 55, 15, 255), width=8)
    draw.rectangle((CENTER - 74, CENTER - 25, CENTER - 64, CENTER - 15), fill=(218, 165, 32, 255))
    draw.rectangle((CENTER + 64, CENTER - 25, CENTER + 74, CENTER - 15), fill=(218, 165, 32, 255))

    # Center Neck Shank
    draw.line([(CENTER, CENTER + 38), (CENTER, CENTER + 65)], fill=(160, 100, 25, 255), width=10)
    draw.line([(CENTER, CENTER + 38), (CENTER, CENTER + 65)], fill=(245, 205, 50, 255), width=4)

    # Rotating 8-Pointed Sunburst Star Rowel (Roda Bintang Berputar)
    r_star = 26
    rx, ry = CENTER, CENTER + 65
    for i in range(8):
        ang = i * (math.pi / 4)
        px1 = rx + math.cos(ang) * r_star
        py1 = ry + math.sin(ang) * r_star
        px2 = rx + math.cos(ang + 0.25) * 8
        py2 = ry + math.sin(ang + 0.25) * 8
        px3 = rx + math.cos(ang - 0.25) * 8
        py3 = ry + math.sin(ang - 0.25) * 8
        draw.polygon([(px2, py2), (px1, py1), (px3, py3)], fill=(255, 225, 80, 255), outline=(139, 69, 19, 255), width=1)

    draw.ellipse((rx - 7, ry - 7, rx + 7, ry + 7), fill=(185, 25, 25, 255), outline=(255, 230, 100, 255), width=2)

    canvas = Image.alpha_composite(canvas, fg)
    return add_antique_gold_frame(canvas)

# ==============================================================================
# 414: Celestial Pegasus (Kuda Perang Putih Bersayap Dewa)
# ==============================================================================
def draw_item_414():
    canvas = create_travian_parchment(halo_color=(255, 225, 70), halo_strength=0.35)
    fg = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)

    # Noble White Stallion Body & Head (Isometric 3/4 Travian Horse View)
    # 1. Divine White & Gold Feathered Wing (Folded & Sweeping)
    wing_feathers = [
        (CENTER - 30, CENTER + 10, CENTER + 30, CENTER - 55),
        (CENTER - 20, CENTER + 20, CENTER + 55, CENTER - 45),
        (CENTER - 10, CENTER + 30, CENTER + 72, CENTER - 30),
        (CENTER, CENTER + 40, CENTER + 80, CENTER - 10),
        (CENTER + 10, CENTER + 48, CENTER + 75, CENTER + 10),
    ]
    for wx1, wy1, wx2, wy2 in wing_feathers:
        draw.polygon([(wx1, wy1 - 8), (wx2, wy2), (wx1, wy1 + 8)],
                     fill=(250, 248, 240, 255), outline=(160, 130, 80, 255), width=2)
        draw.polygon([(wx1 + 4, wy1 - 4), (wx2 - 6, wy2 + 2), (wx1 + 4, wy1 + 4)], fill=(255, 255, 255, 255))

    # 2. White Horse Torso & Neck
    # Torso
    draw.ellipse((CENTER - 65, CENTER - 10, CENTER + 15, CENTER + 55), fill=(245, 245, 248, 255), outline=(120, 115, 110, 255), width=2)
    # Muscular Neck
    draw.polygon([
        (CENTER - 35, CENTER + 15),
        (CENTER - 15, CENTER - 45),
        (CENTER + 15, CENTER - 40),
        (CENTER, CENTER + 25)
    ], fill=(245, 245, 248, 255), outline=(120, 115, 110, 255), width=2)

    # Head
    draw.polygon([
        (CENTER - 10, CENTER - 45),
        (CENTER + 15, CENTER - 65),
        (CENTER + 35, CENTER - 50),
        (CENTER + 15, CENTER - 35)
    ], fill=(250, 250, 252, 255), outline=(120, 115, 110, 255), width=2)

    # 3. Imperial Gold Barding, Bridle & Champron
    # Gold head armor
    draw.polygon([
        (CENTER, CENTER - 50),
        (CENTER + 15, CENTER - 64),
        (CENTER + 25, CENTER - 55),
        (CENTER + 10, CENTER - 42)
    ], fill=(218, 165, 32, 255), outline=(120, 75, 15, 255), width=1)
    # Red horsehair head crest
    draw.line([(CENTER + 10, CENTER - 65), (CENTER + 20, CENTER - 78)], fill=(195, 25, 25, 255), width=4)

    # 4. Royal Crimson & Gold Saddle Caparison
    draw.rectangle((CENTER - 45, CENTER + 10, CENTER - 5, CENTER + 45), fill=(160, 20, 30, 255), outline=(218, 165, 32, 255), width=2)
    draw.ellipse((CENTER - 28, CENTER + 24, CENTER - 22, CENTER + 30), fill=(245, 205, 50, 255))

    canvas = Image.alpha_composite(canvas, fg)
    return add_antique_gold_frame(canvas)

# ==============================================================================
# Main Generation Runner
# ==============================================================================
GENERATORS = {
    210: draw_item_210,
    401: draw_item_401,
    402: draw_item_402,
    403: draw_item_403,
    404: draw_item_404,
    405: draw_item_405,
    406: draw_item_406,
    407: draw_item_407,
    408: draw_item_408,
    409: draw_item_409,
    410: draw_item_410,
    411: draw_item_411,
    412: draw_item_412,
    413: draw_item_413,
    414: draw_item_414,
}

if __name__ == "__main__":
    print("Generating Authentic Majestic Travian-Themed Icons...")
    for item_id, gen_fn in GENERATORS.items():
        out_path = os.path.join(OUTPUT_DIR, f"item{item_id}.png")
        img = gen_fn()
        img.save(out_path, format="PNG", optimize=True)
        print(f"Generated {out_path} ({img.size[0]}x{img.size[1]})")

    print("All 15 authentic Travian-themed legendary icons successfully generated!")

