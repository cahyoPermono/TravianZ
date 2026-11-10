#!/usr/bin/env python3
"""
Generate High-Resolution, Dramatic Fantasy RPG Icons for Tier 4 Legendary Equipment in TravianZ.
Uses layered geometric shapes, metallic gradients, radiant Gaussian-blurred auras,
specular highlights, and cosmic/magical particles.
"""

import os
import math
import random
from PIL import Image, ImageDraw, ImageFilter, ImageEnhance

os.makedirs('img/hero/legendary', exist_ok=True)

SIZE = 256
CENTER = SIZE // 2

def create_base_canvas():
    """Create a dark, moody circular pedestal canvas with radial glow."""
    canvas = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    bg = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(bg)
    # Dark rounded pedestal
    draw.rounded_rectangle((12, 12, SIZE - 12, SIZE - 12), radius=36, fill=(16, 20, 30, 245), outline=(60, 45, 20, 200), width=3)
    return canvas, bg

def add_radiant_aura(canvas, color, radius=40, blur=25):
    """Add an ethereal glowing aura behind the item."""
    glow = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(glow)
    draw.ellipse((CENTER - radius, CENTER - radius, CENTER + radius, CENTER + radius), fill=color)
    glow = glow.filter(ImageFilter.GaussianBlur(blur))
    return Image.alpha_composite(canvas, glow)

def add_magical_sparks(draw, count=25, seed=42, colors=[(255, 215, 0, 200), (255, 255, 255, 240), (255, 140, 0, 180)]):
    """Scatter glittering stars and energy sparkles."""
    rng = random.Random(seed)
    for _ in range(count):
        x = rng.randint(24, SIZE - 24)
        y = rng.randint(24, SIZE - 24)
        r = rng.randint(1, 3)
        col = rng.choice(colors)
        draw.ellipse((x - r, y - r, x + r, y + r), fill=col)
        if r >= 2:
            # 4-point sparkle cross
            s = r * 2
            draw.line((x - s, y, x + s, y), fill=col, width=1)
            draw.line((x, y - s, x, y + s), fill=col, width=1)

def add_ornate_border(im, glow_color=(243, 156, 18, 255)):
    """Add a lavish golden filigree border with corner gems."""
    border = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(border)
    # Outer gold rim
    draw.rounded_rectangle((10, 10, SIZE - 10, SIZE - 10), radius=32, outline=(218, 165, 32, 230), width=3)
    draw.rounded_rectangle((14, 14, SIZE - 14, SIZE - 14), radius=28, outline=(255, 215, 0, 120), width=1)
    # Corner gems (Ruby/Amber)
    corners = [(18, 18), (SIZE - 18, 18), (18, SIZE - 18), (SIZE - 18, SIZE - 18)]
    for cx, cy in corners:
        draw.ellipse((cx - 5, cy - 5, cx + 5, cy + 5), fill=(220, 20, 60, 255), outline=(255, 215, 0, 255), width=1)
        draw.ellipse((cx - 2, cy - 2, cx, cy), fill=(255, 200, 200, 220))
    return Image.alpha_composite(im, border)

# ==============================================================================
# 401: Crown of the Eternal Pantheon (Mahkota Dewata Abadi)
# ==============================================================================
def draw_item_401():
    canvas, bg = create_base_canvas()
    canvas = Image.alpha_composite(canvas, bg)
    canvas = add_radiant_aura(canvas, (255, 180, 20, 140), radius=55, blur=28)
    
    fg = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)
    
    # Sunburst rays behind crown
    for deg in range(0, 360, 18):
        rad = math.radians(deg)
        x1 = CENTER + math.cos(rad) * 45
        y1 = (CENTER - 10) + math.sin(rad) * 45
        x2 = CENTER + math.cos(rad) * 85
        y2 = (CENTER - 10) + math.sin(rad) * 85
        draw.line((x1, y1, x2, y2), fill=(255, 215, 0, 100), width=2)
        
    # Crown Base Arch
    cy = CENTER + 25
    draw.ellipse((CENTER - 65, cy - 15, CENTER + 65, cy + 25), fill=(180, 130, 20, 255), outline=(255, 230, 120, 255), width=3)
    
    # Crown Spikes / Spires
    spires = [
        # (tip_x, tip_y, base_left, base_right)
        (CENTER, cy - 90, CENTER - 20, CENTER + 20),           # Central highest spire
        (CENTER - 35, cy - 70, CENTER - 50, CENTER - 20),      # Left mid spire
        (CENTER + 35, cy - 70, CENTER + 20, CENTER + 50),      # Right mid spire
        (CENTER - 60, cy - 45, CENTER - 70, CENTER - 45),      # Left wing spire
        (CENTER + 60, cy - 45, CENTER + 45, CENTER + 70),      # Right wing spire
    ]
    for tx, ty, bl, br in spires:
        draw.polygon([(bl, cy), (br, cy), (tx, ty)], fill=(245, 185, 30, 255), outline=(255, 240, 150, 255))
        # Spire inner highlight
        draw.line((CENTER, cy, tx, ty), fill=(255, 255, 220, 180), width=2)
        # Gem on spire tip
        draw.ellipse((tx - 6, ty - 6, tx + 6, ty + 6), fill=(0, 200, 255, 255), outline=(255, 255, 255, 255), width=1)
        draw.point((tx - 2, ty - 2), fill=(255, 255, 255, 255))
        
    # Huge Central Royal Sapphire on crown base
    draw.polygon([(CENTER, cy - 12), (CENTER + 18, cy + 5), (CENTER, cy + 22), (CENTER - 18, cy + 5)], fill=(30, 144, 255, 255), outline=(255, 255, 255, 255), width=2)
    draw.polygon([(CENTER, cy - 6), (CENTER + 9, cy + 4), (CENTER, cy + 14), (CENTER - 9, cy + 4)], fill=(135, 206, 250, 255))
    
    # Side Rubies
    for gx in [CENTER - 38, CENTER + 38]:
        draw.ellipse((gx - 8, cy - 2, gx + 8, cy + 14), fill=(220, 20, 60, 255), outline=(255, 215, 0, 255), width=2)
        draw.ellipse((gx - 3, cy + 1, gx + 1, cy + 5), fill=(255, 200, 200, 255))

    add_magical_sparks(draw, 30, 401)
    canvas = Image.alpha_composite(canvas, fg)
    return add_ornate_border(canvas)

# ==============================================================================
# 402: War-Crown of Supreme Command (Mahkota Komando Perang)
# ==============================================================================
def draw_item_402():
    canvas, bg = create_base_canvas()
    canvas = Image.alpha_composite(canvas, bg)
    canvas = add_radiant_aura(canvas, (230, 40, 20, 130), radius=55, blur=28)
    
    fg = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)
    
    # Crimson War Plume / Crest (Centurion style)
    for i in range(-50, 51, 6):
        h = 50 - abs(i) * 0.4
        draw.polygon([(CENTER + i - 3, CENTER - 25), (CENTER + i + 3, CENTER - 25), (CENTER + i, CENTER - 25 - h)], fill=(200 + abs(i), 20, 30, 240))
    
    # Heavy Helmet Visor / Brow Plate (Dark Steel & Blood Gold)
    cy = CENTER + 10
    draw.polygon([(CENTER - 60, cy - 20), (CENTER + 60, cy - 20), (CENTER + 48, cy + 60), (CENTER - 48, cy + 60)], fill=(45, 52, 60, 255), outline=(218, 165, 32, 255), width=3)
    
    # Golden Warlord Horns / Spikes on sides
    draw.polygon([(CENTER - 55, cy - 10), (CENTER - 95, cy - 50), (CENTER - 50, cy + 10)], fill=(218, 165, 32, 255), outline=(255, 215, 0, 255), width=2)
    draw.polygon([(CENTER + 55, cy - 10), (CENTER + 95, cy - 50), (CENTER + 50, cy + 10)], fill=(218, 165, 32, 255), outline=(255, 215, 0, 255), width=2)
    
    # Glowing Demonic / War Eye Slits (T-Visor)
    draw.rectangle((CENTER - 36, cy + 5, CENTER + 36, cy + 15), fill=(255, 40, 20, 255))
    draw.rectangle((CENTER - 6, cy + 15, CENTER + 6, cy + 45), fill=(255, 40, 20, 255))
    draw.rectangle((CENTER - 32, cy + 7, CENTER + 32, cy + 13), fill=(255, 220, 100, 255)) # fiery eye core
    
    # Steel Cheekguards
    draw.polygon([(CENTER - 45, cy + 20), (CENTER - 25, cy + 65), (CENTER - 45, cy + 60)], fill=(60, 70, 80, 255), outline=(218, 165, 32, 255), width=2)
    draw.polygon([(CENTER + 45, cy + 20), (CENTER + 25, cy + 65), (CENTER + 45, cy + 60)], fill=(60, 70, 80, 255), outline=(218, 165, 32, 255), width=2)
    
    add_magical_sparks(draw, 25, 402, [(255, 69, 0, 220), (255, 215, 0, 240)])
    canvas = Image.alpha_composite(canvas, fg)
    return add_ornate_border(canvas)

# ==============================================================================
# 403: Visage of the Undying Phoenix (Topeng Phoenix Abadi)
# ==============================================================================
def draw_item_403():
    canvas, bg = create_base_canvas()
    canvas = Image.alpha_composite(canvas, bg)
    canvas = add_radiant_aura(canvas, (255, 120, 0, 150), radius=60, blur=30)
    
    fg = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)
    
    # Spreading Phoenix Wings of Fire
    for side in [-1, 1]:
        for i, (dx, dy, w) in enumerate([(40, -10, 50), (60, -35, 65), (75, -60, 60), (85, -80, 45)]):
            draw.polygon([(CENTER, CENTER + 20), (CENTER + side * dx, CENTER + dy), (CENTER + side * (dx + 25), CENTER + dy - 20)], fill=(255, 80 + i * 35, 10, 210))
            
    # Golden Bird Mask Face
    cy = CENTER + 10
    draw.polygon([(CENTER, cy + 55), (CENTER + 42, cy - 15), (CENTER + 25, cy - 45), (CENTER - 25, cy - 45), (CENTER - 42, cy - 15)], fill=(255, 190, 30, 255), outline=(255, 245, 180, 255), width=2)
    
    # Sharp Golden Beak
    draw.polygon([(CENTER - 14, cy + 10), (CENTER + 14, cy + 10), (CENTER, cy + 65)], fill=(230, 140, 10, 255), outline=(255, 255, 220, 255), width=2)
    
    # Blazing Phoenix Eyes
    for side in [-1, 1]:
        ex = CENTER + side * 20
        draw.polygon([(ex - side * 8, cy - 10), (ex + side * 14, cy - 20), (ex, cy - 2)], fill=(0, 255, 255, 255), outline=(255, 255, 255, 255))
        draw.ellipse((ex - 2, cy - 12, ex + 2, cy - 8), fill=(255, 255, 255, 255))
        
    # Forehead Phoenix Sun Crest
    draw.ellipse((CENTER - 15, cy - 50, CENTER + 15, cy - 20), fill=(255, 50, 0, 255), outline=(255, 215, 0, 255), width=2)
    draw.ellipse((CENTER - 8, cy - 43, CENTER + 8, cy - 27), fill=(255, 240, 100, 255))

    add_magical_sparks(draw, 35, 403, [(255, 100, 0, 220), (255, 230, 0, 240), (255, 255, 255, 255)])
    canvas = Image.alpha_composite(canvas, fg)
    return add_ornate_border(canvas)

# ==============================================================================
# 404: Aegis Plate of the World-Eater (Zirah Sisik Naga Purba)
# ==============================================================================
def draw_item_404():
    canvas, bg = create_base_canvas()
    canvas = Image.alpha_composite(canvas, bg)
    canvas = add_radiant_aura(canvas, (180, 40, 220, 140), radius=55, blur=28)
    
    fg = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)
    
    # Spiky Dragon Pauldrons (Shoulders)
    draw.polygon([(CENTER - 75, CENTER - 50), (CENTER - 30, CENTER - 45), (CENTER - 50, CENTER + 10)], fill=(75, 25, 95, 255), outline=(218, 165, 32, 255), width=2)
    draw.polygon([(CENTER + 75, CENTER - 50), (CENTER + 30, CENTER - 45), (CENTER + 50, CENTER + 10)], fill=(75, 25, 95, 255), outline=(218, 165, 32, 255), width=2)
    
    # Pauldron Spikes
    draw.polygon([(CENTER - 75, CENTER - 50), (CENTER - 95, CENTER - 75), (CENTER - 60, CENTER - 45)], fill=(218, 165, 32, 255))
    draw.polygon([(CENTER + 75, CENTER - 50), (CENTER + 95, CENTER - 75), (CENTER + 60, CENTER - 45)], fill=(218, 165, 32, 255))
    
    # Central Dragon-Scale Cuirass
    cy = CENTER + 5
    draw.polygon([(CENTER - 45, cy - 45), (CENTER + 45, cy - 45), (CENTER + 35, cy + 55), (CENTER, cy + 70), (CENTER - 35, cy + 55)], fill=(35, 15, 50, 255), outline=(245, 170, 30, 255), width=3)
    
    # Layered Dragon Scales with Molten Purple/Orange glow
    for row in range(5):
        ry = cy - 25 + row * 18
        scales_in_row = 4 - (row % 2)
        sw = 16
        for s in range(scales_in_row):
            sx = CENTER - 25 + s * sw + (8 if row % 2 else 0)
            draw.polygon([(sx, ry), (sx + 8, ry + 12), (sx - 8, ry + 12)], fill=(120, 40, 160, 255), outline=(255, 120, 20, 255), width=1)
            
    # Central Dragon Heart Core (Glowing Magenta/Violet Gem)
    draw.polygon([(CENTER, cy - 25), (CENTER + 16, cy - 10), (CENTER, cy + 10), (CENTER - 16, cy - 10)], fill=(255, 0, 128, 255), outline=(255, 255, 255, 255), width=2)
    draw.ellipse((CENTER - 6, cy - 16, CENTER + 6, cy - 4), fill=(255, 200, 255, 255))
    
    add_magical_sparks(draw, 25, 404, [(255, 0, 255, 200), (255, 165, 0, 220)])
    canvas = Image.alpha_composite(canvas, fg)
    return add_ornate_border(canvas)

# ==============================================================================
# 405: Cuirass of the Colossus (Baju Zirah Raksasa Pelindung)
# ==============================================================================
def draw_item_405():
    canvas, bg = create_base_canvas()
    canvas = Image.alpha_composite(canvas, bg)
    canvas = add_radiant_aura(canvas, (30, 144, 255, 140), radius=55, blur=28)
    
    fg = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)
    
    # Massive Tiered Shoulders (Heavy Titan Plate)
    for side in [-1, 1]:
        draw.polygon([(CENTER + side * 40, CENTER - 50), (CENTER + side * 90, CENTER - 40), (CENTER + side * 75, CENTER + 5), (CENTER + side * 30, CENTER - 5)], fill=(30, 60, 95, 255), outline=(218, 165, 32, 255), width=3)
        draw.polygon([(CENTER + side * 45, CENTER - 45), (CENTER + side * 85, CENTER - 35), (CENTER + side * 70, CENTER + 0), (CENTER + side * 35, CENTER - 8)], fill=(45, 90, 140, 255))
        
    # Colossus Chestplate
    cy = CENTER + 10
    draw.polygon([(CENTER - 45, cy - 50), (CENTER + 45, cy - 50), (CENTER + 38, cy + 60), (CENTER, cy + 75), (CENTER - 38, cy + 60)], fill=(20, 40, 65, 255), outline=(218, 165, 32, 255), width=3)
    
    # Ancient Blue Titan Runes engraved on chest
    draw.line((CENTER - 25, cy - 25, CENTER, cy - 5), fill=(0, 255, 255, 255), width=3)
    draw.line((CENTER + 25, cy - 25, CENTER, cy - 5), fill=(0, 255, 255, 255), width=3)
    draw.line((CENTER, cy - 5, CENTER, cy + 45), fill=(0, 255, 255, 255), width=3)
    draw.ellipse((CENTER - 18, cy - 35, CENTER + 18, cy + 5), outline=(0, 255, 255, 255), width=2)
    draw.ellipse((CENTER - 8, cy - 18, CENTER + 8, cy - 2), fill=(100, 230, 255, 255))
    
    add_magical_sparks(draw, 25, 405, [(0, 255, 255, 220), (255, 255, 255, 255)])
    canvas = Image.alpha_composite(canvas, fg)
    return add_ornate_border(canvas)

# ==============================================================================
# 406: Sovereign's Warblade (Pedang Penguasa Perang)
# ==============================================================================
def draw_item_406():
    canvas, bg = create_base_canvas()
    canvas = Image.alpha_composite(canvas, bg)
    canvas = add_radiant_aura(canvas, (255, 215, 0, 160), radius=60, blur=30)
    
    fg = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)
    
    # Diagonal Epic Two-Handed Broadsword (Top-Right to Bottom-Left)
    # Pommel & Handle
    hx, hy = CENTER - 55, CENTER + 65
    draw.ellipse((hx - 10, hy - 10, hx + 10, hy + 10), fill=(218, 165, 32, 255), outline=(255, 255, 255, 255), width=2)
    draw.ellipse((hx - 4, hy - 4, hx + 4, hy + 4), fill=(220, 20, 60, 255)) # Pommel ruby
    
    # Grip (Spiral wrapped)
    draw.line((hx, hy, CENTER - 25, CENTER + 35), fill=(100, 50, 20, 255), width=8)
    draw.line((hx, hy, CENTER - 25, CENTER + 35), fill=(255, 215, 0, 200), width=2)
    
    # Ornate Winged Crossguard
    gx, gy = CENTER - 22, CENTER + 32
    draw.polygon([(gx - 30, gy - 10), (gx + 10, gy + 30), (gx + 18, gy + 22), (gx - 22, gy - 18)], fill=(240, 180, 20, 255), outline=(255, 255, 255, 255), width=2)
    draw.polygon([(gx + 30, gy + 10), (gx - 10, gy - 30), (gx - 18, gy - 22), (gx + 22, gy + 18)], fill=(240, 180, 20, 255), outline=(255, 255, 255, 255), width=2)
    
    # Guard Central Gem
    draw.ellipse((gx - 7, gy - 7, gx + 7, gy + 7), fill=(0, 200, 255, 255), outline=(255, 255, 255, 255), width=1)
    
    # Magnificent Glowing Blade (pointing to top right)
    tip_x, tip_y = CENTER + 75, CENTER - 65
    bx, by = gx + 5, gy - 5
    draw.polygon([(bx - 12, by - 6), (bx + 6, by + 12), (tip_x, tip_y)], fill=(240, 245, 255, 255), outline=(255, 215, 0, 255), width=2)
    # Blade Full Center Fuller & Golden Runes
    draw.line((bx, by, tip_x, tip_y), fill=(255, 215, 0, 255), width=3)
    draw.line((bx, by, tip_x, tip_y), fill=(255, 255, 255, 255), width=1) # core gleam
    
    add_magical_sparks(draw, 35, 406, [(255, 215, 0, 255), (255, 255, 255, 255), (255, 140, 0, 220)])
    canvas = Image.alpha_composite(canvas, fg)
    return add_ornate_border(canvas)

# ==============================================================================
# 407: Gungnir, The Stormbringer (Tombak Badai Langit)
# ==============================================================================
def draw_item_407():
    canvas, bg = create_base_canvas()
    canvas = Image.alpha_composite(canvas, bg)
    canvas = add_radiant_aura(canvas, (0, 200, 255, 160), radius=55, blur=28)
    
    fg = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)
    
    # Lightning Arcs around spear
    for _ in range(8):
        lx, ly = CENTER + random.randint(-40, 40), CENTER + random.randint(-40, 40)
        draw.line((lx, ly, lx + random.randint(-20, 20), ly + random.randint(-20, 20)), fill=(0, 255, 255, 200), width=2)
        
    # Long Ash Shaft (Diagonal)
    draw.line([(CENTER - 60, CENTER + 80), (CENTER + 40, CENTER - 40)], fill=(139, 69, 19, 255), width=6)
    draw.line([(CENTER - 60, CENTER + 80), (CENTER + 40, CENTER - 40)], fill=(218, 165, 32, 200), width=2)
    
    # Massive Ornate Spearhead (Gungnir)
    sp_base_x, sp_base_y = CENTER + 30, CENTER - 30
    sp_tip_x, sp_tip_y   = CENTER + 80, CENTER - 80
    draw.polygon([(sp_base_x - 18, sp_base_y + 12), (sp_tip_x, sp_tip_y), (sp_base_x + 12, sp_base_y - 18), (sp_base_x, sp_base_y)], fill=(220, 240, 255, 255), outline=(0, 220, 255, 255), width=2)
    
    # Central Blue Lightning Core
    draw.line((sp_base_x, sp_base_y, sp_tip_x, sp_tip_y), fill=(0, 255, 255, 255), width=3)
    draw.line((sp_base_x, sp_base_y, sp_tip_x, sp_tip_y), fill=(255, 255, 255, 255), width=1)
    
    add_magical_sparks(draw, 30, 407, [(0, 255, 255, 240), (255, 255, 255, 255)])
    canvas = Image.alpha_composite(canvas, fg)
    return add_ornate_border(canvas)

# ==============================================================================
# 408: Worldbreaker Warhammer (Palu Penghancur Benteng)
# ==============================================================================
def draw_item_408():
    canvas, bg = create_base_canvas()
    canvas = Image.alpha_composite(canvas, bg)
    canvas = add_radiant_aura(canvas, (255, 69, 0, 160), radius=55, blur=28)
    
    fg = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)
    
    # Shaft
    draw.line([(CENTER - 50, CENTER + 75), (CENTER + 20, CENTER - 10)], fill=(70, 70, 70, 255), width=8)
    draw.line([(CENTER - 50, CENTER + 75), (CENTER + 20, CENTER - 10)], fill=(218, 165, 32, 255), width=2)
    
    # Colossal Anvil Hammer Head with Molten Cracks
    hx, hy = CENTER + 30, CENTER - 35
    draw.polygon([(hx - 45, hy - 25), (hx + 25, hy - 45), (hx + 45, hy + 25), (hx - 25, hy + 45)], fill=(45, 45, 50, 255), outline=(255, 140, 0, 255), width=3)
    
    # Molten Lava Rune Veins in hammer face
    draw.line((hx - 30, hy - 15, hx + 15, hy - 30), fill=(255, 69, 0, 255), width=3)
    draw.line((hx - 15, hy + 30, hx + 30, hy + 15), fill=(255, 69, 0, 255), width=3)
    draw.line((hx - 20, hy, hx + 20, hy), fill=(255, 215, 0, 255), width=4)
    draw.line((hx - 20, hy, hx + 20, hy), fill=(255, 255, 255, 255), width=1)
    
    add_magical_sparks(draw, 30, 408, [(255, 69, 0, 240), (255, 215, 0, 255)])
    canvas = Image.alpha_composite(canvas, fg)
    return add_ornate_border(canvas)

# ==============================================================================
# 409: Horn of Global Dominion (Terompet Penakluk Jagat)
# ==============================================================================
def draw_item_409():
    canvas, bg = create_base_canvas()
    canvas = Image.alpha_composite(canvas, bg)
    canvas = add_radiant_aura(canvas, (218, 165, 32, 140), radius=55, blur=28)
    
    fg = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)
    
    # Curved Dragon Bone Horn with Gold Bands
    points = []
    for t in range(50):
        frac = t / 50.0
        angle = math.pi * 0.8 * frac
        r = 75 - frac * 35
        x = CENTER + math.cos(angle) * r - 10
        y = CENTER + math.sin(angle) * r + 15
        points.append((x, y))
        
    for i in range(len(points) - 1):
        thickness = int(14 + (1 - i / len(points)) * 26)
        draw.line([points[i], points[i+1]], fill=(245, 240, 220, 255), width=thickness)
        if i % 8 == 0:
            # Gold banding rings
            draw.line([points[i], points[i+1]], fill=(218, 165, 32, 255), width=thickness + 4)
            
    # Huge Gold Horn Bell (Opening)
    bx, by = points[0]
    draw.ellipse((bx - 20, by - 26, bx + 20, by + 26), fill=(180, 130, 20, 255), outline=(255, 215, 0, 255), width=3)
    draw.ellipse((bx - 12, by - 18, bx + 12, by + 18), fill=(40, 20, 10, 255))
    
    # Soundwaves of Conquest radiating
    for sw in [35, 55, 75]:
        draw.arc((bx - sw, by - sw, bx + sw, by + sw), start=120, end=240, fill=(255, 215, 0, 140), width=2)
        
    add_magical_sparks(draw, 25, 409)
    canvas = Image.alpha_composite(canvas, fg)
    return add_ornate_border(canvas)

# ==============================================================================
# 410: Astral Chrono-Sextant (Sekstan Pengembara Dimensi)
# ==============================================================================
def draw_item_410():
    canvas, bg = create_base_canvas()
    canvas = Image.alpha_composite(canvas, bg)
    canvas = add_radiant_aura(canvas, (138, 43, 226, 150), radius=55, blur=28)
    
    fg = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)
    
    # Ornate Astrolabe / Sextant Rings
    for r in [65, 48, 30]:
        draw.ellipse((CENTER - r, CENTER - r, CENTER + r, CENTER + r), outline=(218, 165, 32, 255), width=2)
        
    # Zodiac / Navigational Graduations
    for deg in range(0, 360, 30):
        rad = math.radians(deg)
        x1 = CENTER + math.cos(rad) * 60
        y1 = CENTER + math.sin(rad) * 60
        x2 = CENTER + math.cos(rad) * 68
        y2 = CENTER + math.sin(rad) * 68
        draw.line((x1, y1, x2, y2), fill=(255, 215, 0, 255), width=2)
        
    # Pivoting Scope & Brass Arm
    draw.line([(CENTER - 50, CENTER + 50), (CENTER + 50, CENTER - 50)], fill=(255, 220, 120, 255), width=4)
    draw.rectangle((CENTER + 25, CENTER - 55, CENTER + 55, CENTER - 25), outline=(255, 215, 0, 255), width=2)
    
    # Glowing Cosmic Orb at center
    draw.ellipse((CENTER - 16, CENTER - 16, CENTER + 16, CENTER + 16), fill=(75, 0, 130, 255), outline=(0, 255, 255, 255), width=2)
    draw.ellipse((CENTER - 6, CENTER - 6, CENTER + 6, CENTER + 6), fill=(255, 255, 255, 255))
    
    add_magical_sparks(draw, 35, 410, [(186, 85, 211, 220), (0, 255, 255, 240), (255, 255, 255, 255)])
    canvas = Image.alpha_composite(canvas, fg)
    return add_ornate_border(canvas)

# ==============================================================================
# 411: Mirror Shield of Reflection (Perisai Cermin Pemantul)
# ==============================================================================
def draw_item_411():
    canvas, bg = create_base_canvas()
    canvas = Image.alpha_composite(canvas, bg)
    canvas = add_radiant_aura(canvas, (0, 255, 255, 140), radius=55, blur=28)
    
    fg = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)
    
    # Diamond / Kite Mirror Shield
    pts = [(CENTER, CENTER - 70), (CENTER + 60, CENTER - 20), (CENTER + 45, CENTER + 60), (CENTER, CENTER + 80), (CENTER - 45, CENTER + 60), (CENTER - 60, CENTER - 20)]
    draw.polygon(pts, fill=(20, 50, 70, 255), outline=(218, 165, 32, 255), width=4)
    
    # Prismatic Mirror Face
    inner_pts = [(CENTER, CENTER - 60), (CENTER + 48, CENTER - 18), (CENTER + 36, CENTER + 50), (CENTER, CENTER + 68), (CENTER - 36, CENTER + 50), (CENTER - 48, CENTER - 18)]
    draw.polygon(inner_pts, fill=(200, 240, 255, 255), outline=(255, 255, 255, 255), width=2)
    
    # Reflective Sheen Sweep
    draw.polygon([(CENTER - 35, CENTER + 45), (CENTER + 15, CENTER - 45), (CENTER + 30, CENTER - 45), (CENTER - 20, CENTER + 45)], fill=(255, 255, 255, 140))
    
    # Central Sun Boss / Crest
    draw.ellipse((CENTER - 18, CENTER - 18, CENTER + 18, CENTER + 18), fill=(218, 165, 32, 255), outline=(255, 255, 255, 255), width=2)
    draw.ellipse((CENTER - 8, CENTER - 8, CENTER + 8, CENTER + 8), fill=(255, 255, 255, 255))
    
    add_magical_sparks(draw, 30, 411, [(0, 255, 255, 220), (255, 255, 255, 255)])
    canvas = Image.alpha_composite(canvas, fg)
    return add_ornate_border(canvas)

# ==============================================================================
# 412: Hermes' Astral Greaves (Sepatu Sayap Astral)
# ==============================================================================
def draw_item_412():
    canvas, bg = create_base_canvas()
    canvas = Image.alpha_composite(canvas, bg)
    canvas = add_radiant_aura(canvas, (50, 205, 50, 140), radius=55, blur=28)
    
    fg = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)
    
    # Pair of Golden Greaves (Shins and Boots)
    for side in [-1, 1]:
        bx = CENTER + side * 28
        # Shin guard
        draw.polygon([(bx - 12, CENTER - 50), (bx + 12, CENTER - 50), (bx + 10, CENTER + 20), (bx - 10, CENTER + 20)], fill=(218, 165, 32, 255), outline=(255, 240, 150, 255), width=2)
        # Boot foot
        draw.polygon([(bx - 10, CENTER + 20), (bx + 10, CENTER + 20), (bx + side * 26, CENTER + 55), (bx - 8, CENTER + 55)], fill=(180, 130, 20, 255), outline=(255, 240, 150, 255), width=2)
        
        # Feathered Wings of Hermes (Sprouting from ankle)
        wx = bx + side * 15
        wy = CENTER + 15
        for f in range(4):
            fw = 35 + f * 10
            draw.polygon([(wx, wy), (wx + side * fw, wy - 20 - f * 10), (wx + side * (fw - 15), wy - 10 - f * 8)], fill=(240, 250, 255, 230), outline=(218, 165, 32, 200), width=1)
            
    add_magical_sparks(draw, 30, 412, [(50, 205, 50, 200), (255, 255, 255, 255)])
    canvas = Image.alpha_composite(canvas, fg)
    return add_ornate_border(canvas)

# ==============================================================================
# 413: Spurs of the Apocalypse (Taji Penunggang Badai)
# ==============================================================================
def draw_item_413():
    canvas, bg = create_base_canvas()
    canvas = Image.alpha_composite(canvas, bg)
    canvas = add_radiant_aura(canvas, (255, 69, 0, 150), radius=55, blur=28)
    
    fg = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)
    
    # Heavy Obsidian & Gold Ankle Clamp
    draw.arc((CENTER - 50, CENTER - 40, CENTER + 50, CENTER + 60), start=30, end=150, fill=(218, 165, 32, 255), width=12)
    draw.arc((CENTER - 46, CENTER - 36, CENTER + 46, CENTER + 56), start=30, end=150, fill=(30, 30, 35, 255), width=6)
    
    # Massive Multi-Pointed Star Rowel (Spur Wheel) radiating flames
    rx, ry = CENTER, CENTER + 25
    for a in range(0, 360, 45):
        rad = math.radians(a)
        x1 = rx + math.cos(rad) * 12
        y1 = ry + math.sin(rad) * 12
        x2 = rx + math.cos(rad) * 45
        y2 = ry + math.sin(rad) * 45
        draw.line((x1, y1, x2, y2), fill=(255, 140, 0, 255), width=5)
        draw.line((x1, y1, x2, y2), fill=(255, 255, 200, 255), width=2)
        
    draw.ellipse((rx - 14, ry - 14, rx + 14, ry + 14), fill=(220, 20, 60, 255), outline=(255, 215, 0, 255), width=2)
    draw.ellipse((rx - 6, ry - 6, rx + 6, ry + 6), fill=(255, 255, 255, 255))
    
    add_magical_sparks(draw, 30, 413, [(255, 69, 0, 220), (255, 215, 0, 240)])
    canvas = Image.alpha_composite(canvas, fg)
    return add_ornate_border(canvas)

# ==============================================================================
# 414: Celestial Pegasus (Pegasus Surgawi)
# ==============================================================================
def draw_item_414():
    canvas, bg = create_base_canvas()
    canvas = Image.alpha_composite(canvas, bg)
    canvas = add_radiant_aura(canvas, (135, 206, 250, 160), radius=60, blur=30)
    
    fg = Image.new('RGBA', (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(fg)
    
    # Huge Majestic Wings Outspread
    for side in [-1, 1]:
        for i in range(5):
            angle = math.radians(200 - i * 15 if side < 0 else -20 + i * 15)
            length = 85 - i * 8
            x1 = CENTER + side * 20
            y1 = CENTER - 10
            x2 = x1 + math.cos(angle) * length
            y2 = y1 + math.sin(angle) * length
            draw.polygon([(x1, y1), (x2, y2), (x2 + side * 15, y2 + 10)], fill=(240, 248, 255, 240), outline=(218, 165, 32, 220), width=1)
            
    # Noble Steed Head & Neck Profile
    draw.polygon([(CENTER - 15, CENTER + 60), (CENTER + 15, CENTER + 60), (CENTER + 20, CENTER - 10), (CENTER + 35, CENTER - 35), (CENTER + 15, CENTER - 45), (CENTER - 15, CENTER - 30)], fill=(250, 250, 255, 255), outline=(218, 165, 32, 255), width=2)
    
    # Golden Barding / Champron (Horse Armor)
    draw.polygon([(CENTER + 12, CENTER - 30), (CENTER + 32, CENTER - 35), (CENTER + 18, CENTER - 45)], fill=(218, 165, 32, 255), outline=(255, 255, 255, 255), width=1)
    
    # Flowing Celestial Starlight Mane
    for my in range(-40, 30, 8):
        draw.line((CENTER - 15, CENTER + my, CENTER - 38, CENTER + my + 15), fill=(0, 230, 255, 255), width=3)
        draw.line((CENTER - 15, CENTER + my, CENTER - 38, CENTER + my + 15), fill=(255, 255, 255, 255), width=1)
        
    add_magical_sparks(draw, 35, 414, [(135, 206, 250, 240), (255, 255, 255, 255)])
    canvas = Image.alpha_composite(canvas, fg)
    return add_ornate_border(canvas)

# Dictionary of generators
GENERATORS = {
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

if __name__ == '__main__':
    for item_id, gen_fn in GENERATORS.items():
        out_path = f'img/hero/legendary/item{item_id}.png'
        img = gen_fn()
        img.save(out_path)
        print(f'Generated {out_path}')
    print('All 14 Tier 4 legendary icons generated successfully!')
