#!/usr/bin/env python3
"""
TravianZ - Crop & Process User Generated Legendary Items
Extracts 15 legendary hero item cards from attachment 2 (media_1794273409423_2147596f.jpg)
Mapped accurately against attachment 1 labels:
- Row 0: #210, #401, #402, #403, #404
- Row 1: #405, #406, #407, #408
- Row 2: #409, #410, #411, #412, #413, #414
Cleans outer background to transparent, centers into 256x256 PNGs.
"""

import os
import numpy as np
from PIL import Image
import scipy.ndimage as ndi

SRC_IMAGE = "/Users/codeemperor/.gemini/antigravity/brain/f6772e82-f14b-4368-aad8-d3219b3cff59/.user_uploaded/media_1794273409423_2147596f.jpg"
OUT_DIR = "img/hero/legendary"
os.makedirs(OUT_DIR, exist_ok=True)

# Precise coordinates (x1, y1, x2, y2)
CARD_BOXES = {
    # Row 0
    "item210": (7, 2, 209, 220),       # World Essence
    "item401": (210, 2, 416, 220),     # Crown of the Eternal Pantheon
    "item402": (417, 2, 612, 220),     # War-Crown of Supreme Command
    "item403": (613, 2, 815, 220),     # Visage of the Undying Phoenix
    "item404": (816, 2, 1016, 220),    # Aegis Plate of the World-Eater (Cosmic Dark)

    # Row 1
    "item405": (210, 221, 408, 443),   # Cuirass of the Colossus
    "item406": (409, 221, 611, 443),   # Sovereign's Warblade
    "item407": (612, 221, 817, 443),   # Gungnir, The Stormbringer
    "item408": (818, 221, 1016, 443),  # Worldbreaker Warhammer

    # Row 2 (6 items in bottom row)
    "item409": (7, 444, 190, 665),     # Horn of Global Dominion
    "item410": (191, 444, 363, 665),   # Astral Chrono-Sextant
    "item411": (364, 444, 526, 665),   # Mirror Shield of Reflection
    "item412": (527, 444, 691, 665),   # Hermes' Astral Greaves
    "item413": (692, 444, 840, 665),   # Spurs of the Apocalypse
    "item414": (841, 444, 1016, 665),  # Celestial Pegasus
}

def make_outside_transparent(im_rgba):
    """
    Flood fills outside border black pixels to alpha=0.
    Keeps everything inside the gold border completely intact.
    """
    arr = np.array(im_rgba)
    h, w, _ = arr.shape

    # Detect dark background pixels (RGB sum < 75 or max < 30)
    is_black = (arr[:, :, 0] < 30) & (arr[:, :, 1] < 30) & (arr[:, :, 2] < 30)

    # Seed from outer perimeter edges
    seed = np.zeros((h, w), dtype=bool)
    seed[0, :] = is_black[0, :]
    seed[-1, :] = is_black[-1, :]
    seed[:, 0] = is_black[:, 0]
    seed[:, -1] = is_black[:, -1]

    # Propagation
    outside = ndi.binary_propagation(seed, mask=is_black)
    arr[outside, 3] = 0

    return Image.fromarray(arr)

def process_and_save():
    src = Image.open(SRC_IMAGE)
    print(f"Loaded source image {SRC_IMAGE} ({src.size[0]}x{src.size[1]})")

    TARGET_SIZE = 256
    MAX_INNER = 250

    for item_name, box in CARD_BOXES.items():
        # 1. Crop raw box
        cropped = src.crop(box).convert("RGBA")

        # 2. Tight bounding box of non-black content
        arr_temp = np.array(cropped)
        content_mask = arr_temp.max(axis=2) > 25
        ys, xs = np.where(content_mask)
        if len(ys) > 0:
            tight_box = (xs.min(), ys.min(), xs.max() + 1, ys.max() + 1)
            cropped = cropped.crop(tight_box)

        # 3. Transparent background outside the gold frame
        cleaned = make_outside_transparent(cropped)

        # 4. Scale preserving aspect ratio into 256x256
        cw, ch = cleaned.size
        scale = MAX_INNER / max(cw, ch)
        nw, nh = int(round(cw * scale)), int(round(ch * scale))
        scaled = cleaned.resize((nw, nh), Image.Resampling.LANCZOS)

        # 5. Composite into center of 256x256 RGBA canvas
        canvas = Image.new("RGBA", (TARGET_SIZE, TARGET_SIZE), (0, 0, 0, 0))
        px = (TARGET_SIZE - nw) // 2
        py = (TARGET_SIZE - nh) // 2
        canvas.paste(scaled, (px, py), scaled)

        out_path = os.path.join(OUT_DIR, f"{item_name}.png")
        canvas.save(out_path, format="PNG", optimize=True)
        print(f"Generated {out_path} ({TARGET_SIZE}x{TARGET_SIZE}) from crop {box}")

if __name__ == "__main__":
    process_and_save()
    print("All 15 legendary item cards successfully processed and saved!")

