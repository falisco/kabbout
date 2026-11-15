# Build the 32-card belote deck with French indices (R/D/V) from the CC0 bridge-size deck (Mesmedir, after Dmitry Fomin).
# With `--en` as third argument: only the 12 court cards, with the deck's own J/Q/K indices (the English screen).
# The card back is not made here: it is the game's own drawing (tools/brand.mjs).
import sys, os
from PIL import Image, ImageDraw, features
src, out = sys.argv[1], sys.argv[2]
EN = '--en' in sys.argv[3:]
os.makedirs(out, exist_ok=True)
SS = 4                                   # supersampling factor
X0, Y0, X1, Y1, SW = 33, 25, 60, 95, 8   # index glyph box and stem width (measured)
SUITS = 'SHDC'
RANKS = {'A': 1, '7': 7, '8': 8, '9': 9, 'T': 10, 'J': 11, 'Q': 12, 'K': 13}
FR = {'J': 'V', 'Q': 'D', 'K': 'R'}
OUT_W = 330

def glyph(letter, color):
    w, h = (X1 - X0 + 1) * SS, (Y1 - Y0 + 1) * SS
    sw = SW * SS
    im = Image.new('RGBA', (w, h), (0, 0, 0, 0)); d = ImageDraw.Draw(im)
    c = color + (255,); clear = (0, 0, 0, 0)
    if letter == 'V':
        cx = w / 2
        d.polygon([(0, 0), (sw * 1.05, 0), (cx, h - sw * 1.7), (w - sw * 1.05, 0), (w, 0), (cx + sw * .62, h), (cx - sw * .62, h)], fill=c)
    elif letter == 'D':
        r = w * .5
        d.rounded_rectangle([0, 0, w - 1, h - 1], r, fill=c); d.rectangle([0, 0, r, h - 1], fill=c)
        ri = max(r - sw, 1)
        d.rounded_rectangle([sw, sw, w - 1 - sw, h - 1 - sw], ri, fill=clear); d.rectangle([sw, sw, sw + ri, h - 1 - sw], fill=clear)
    elif letter == 'R':
        bh = int(h * .54); r = min(w * .5, bh * .5)
        d.rectangle([0, 0, sw, h - 1], fill=c)
        d.rounded_rectangle([0, 0, w - 1, bh], r, fill=c); d.rectangle([0, 0, r, bh], fill=c)
        ri = max(r - sw, 1)
        d.rounded_rectangle([sw, sw, w - 1 - sw, bh - sw], ri, fill=clear); d.rectangle([sw, sw, sw + ri, bh - sw], fill=clear)
        jx = w * .38
        d.polygon([(jx, bh - sw * .9), (jx + sw * 1.15, bh - sw * .9), (w, h), (w - sw * 1.2, h)], fill=c)
    return im.resize((X1 - X0 + 1, Y1 - Y0 + 1), Image.LANCZOS)

def relabel(im, letter):
    px = im.load()
    # ink colour of the old letter: its darkest pixel inside the glyph box
    color = min((px[x, y][:3] for x in range(X0, X1 + 1) for y in range(Y0, Y1 + 1)), key=sum)
    bg = px[X0 - 6, Y0 + 2]
    for _ in range(2):                                     # top-left, then bottom-right (rotated 180°)
        d = ImageDraw.Draw(im)
        d.rectangle([X0 - 2, Y0 - 2, X1 + 3, Y1 + 8], fill=bg)   # +8 also clears the Q's tail; suit pip starts at y=108
        g = glyph(letter, color)
        im.alpha_composite(g, (X0, Y0))
        im = im.rotate(180)
        px = im.load()
    return im

if EN:
    # the court cards as the source deck draws them, with their J/Q/K indices
    for s in SUITS:
        for r in FR:
            im = Image.open(f'{src}/{s}-{RANKS[r]}.png').convert('RGBA')
            h = round(im.height * OUT_W / im.width)
            im.resize((OUT_W, h), Image.LANCZOS).save(f'{out}/{r}{s}.webp', 'WEBP', quality=88, method=6)
else:
    for s in SUITS:
        for r, n in RANKS.items():
            im = Image.open(f'{src}/{s}-{n}.png').convert('RGBA')
            if r in FR: im = relabel(im, FR[r])
            h = round(im.height * OUT_W / im.width)
            im.resize((OUT_W, h), Image.LANCZOS).save(f'{out}/{r}{s}.webp', 'WEBP', quality=88, method=6)
print('webp', features.check('webp'), len([f for f in os.listdir(out) if f.endswith('.webp')]), 'files')
