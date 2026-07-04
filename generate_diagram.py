"""
CPRAS Architectural Framework Diagram Generator
Generates a research-paper-ready PNG image of the system architecture.
"""
from PIL import Image, ImageDraw, ImageFont
import os

# --- Canvas Setup ---
WIDTH, HEIGHT = 1400, 1600
img = Image.new('RGB', (WIDTH, HEIGHT), '#ffffff')
draw = ImageDraw.Draw(img)

# --- Font Setup ---
# Try to use a clean system font, fallback to default
font_paths = [
    "C:/Windows/Fonts/segoeui.ttf",    # Segoe UI Regular
    "C:/Windows/Fonts/arial.ttf",       # Arial Regular
    "C:/Windows/Fonts/calibri.ttf",     # Calibri Regular
]
font_bold_paths = [
    "C:/Windows/Fonts/segoeuib.ttf",   # Segoe UI Bold
    "C:/Windows/Fonts/arialbd.ttf",    # Arial Bold
    "C:/Windows/Fonts/calibrib.ttf",   # Calibri Bold
]

def load_font(paths, size):
    for p in paths:
        if os.path.exists(p):
            return ImageFont.truetype(p, size)
    return ImageFont.load_default()

font_title = load_font(font_bold_paths, 36)
font_subtitle = load_font(font_paths, 22)
font_box_title = load_font(font_bold_paths, 24)
font_box_desc = load_font(font_paths, 20)
font_small = load_font(font_paths, 18)
font_label = load_font(font_bold_paths, 20)

# --- Color Palette ---
COLORS = {
    'blue':   {'bg': '#EFF6FF', 'border': '#93C5FD', 'text': '#1E40AF'},
    'gray':   {'bg': '#F8FAFC', 'border': '#CBD5E1', 'text': '#334155'},
    'orange': {'bg': '#FFF7ED', 'border': '#FDBA74', 'text': '#9A3412'},
    'green':  {'bg': '#F0FDF4', 'border': '#86EFAC', 'text': '#166534'},
    'red':    {'bg': '#FEF2F2', 'border': '#FCA5A5', 'text': '#991B1B'},
    'purple': {'bg': '#FAF5FF', 'border': '#D8B4FE', 'text': '#6B21A8'},
}
ARROW_COLOR = '#64748B'
TITLE_COLOR = '#0F172A'
SUBTITLE_COLOR = '#475569'

# --- Helper Functions ---
def hex_to_rgb(hex_color):
    hex_color = hex_color.lstrip('#')
    return tuple(int(hex_color[i:i+2], 16) for i in (0, 2, 4))

def draw_rounded_rect(x, y, w, h, radius, fill, outline, outline_width=3):
    """Draw a rounded rectangle."""
    fill_rgb = hex_to_rgb(fill)
    outline_rgb = hex_to_rgb(outline)
    # Draw filled rounded rectangle
    draw.rounded_rectangle(
        [x, y, x + w, y + h],
        radius=radius,
        fill=fill_rgb,
        outline=outline_rgb,
        width=outline_width
    )

def draw_centered_text(text, cx, y, font, color):
    """Draw text centered at cx."""
    color_rgb = hex_to_rgb(color)
    bbox = draw.textbbox((0, 0), text, font=font)
    tw = bbox[2] - bbox[0]
    draw.text((cx - tw // 2, y), text, fill=color_rgb, font=font)

def draw_arrow(x1, y1, x2, y2, color=ARROW_COLOR, width=3):
    """Draw a line with an arrowhead."""
    color_rgb = hex_to_rgb(color)
    draw.line([(x1, y1), (x2, y2)], fill=color_rgb, width=width)
    # Arrowhead
    arrow_size = 12
    if y2 > y1:  # downward
        draw.polygon([
            (x2, y2),
            (x2 - arrow_size, y2 - arrow_size * 1.5),
            (x2 + arrow_size, y2 - arrow_size * 1.5)
        ], fill=color_rgb)
    elif y2 < y1:  # upward
        draw.polygon([
            (x2, y2),
            (x2 - arrow_size, y2 + arrow_size * 1.5),
            (x2 + arrow_size, y2 + arrow_size * 1.5)
        ], fill=color_rgb)

def draw_horizontal_double_arrow(x1, y, x2, color=ARROW_COLOR, width=2):
    """Draw a horizontal line with arrowheads on both ends (dashed style)."""
    color_rgb = hex_to_rgb(color)
    # Draw dashed line
    dash_len = 12
    gap_len = 8
    cx = x1
    while cx < x2:
        end = min(cx + dash_len, x2)
        draw.line([(cx, y), (end, y)], fill=color_rgb, width=width)
        cx = end + gap_len
    # Left arrowhead
    arrow_size = 10
    draw.polygon([
        (x1, y),
        (x1 + arrow_size * 1.5, y - arrow_size),
        (x1 + arrow_size * 1.5, y + arrow_size)
    ], fill=color_rgb)
    # Right arrowhead
    draw.polygon([
        (x2, y),
        (x2 - arrow_size * 1.5, y - arrow_size),
        (x2 - arrow_size * 1.5, y + arrow_size)
    ], fill=color_rgb)

# --- Layout Constants ---
CX = WIDTH // 2  # Center X
BOX_W = 500
BOX_H = 110
SMALL_BOX_W = 420
SMALL_BOX_H = 120
GAP = 80  # Vertical gap between boxes
RADIUS = 20

# === DRAW DIAGRAM ===

# --- Title ---
draw_centered_text("CPRAS ARCHITECTURAL FRAMEWORK", CX, 40, font_title, TITLE_COLOR)
draw_centered_text("Centralized Profiling and Record Authentication System", CX, 90, font_subtitle, SUBTITLE_COLOR)

# --- ROW 1: Web Server (Top, centered) ---
row1_y = 160
draw_rounded_rect(CX - BOX_W // 2, row1_y, BOX_W, BOX_H, RADIUS,
                  COLORS['blue']['bg'], COLORS['blue']['border'])
draw_centered_text("PHP 8.x + Apache", CX, row1_y + 20, font_box_title, COLORS['blue']['text'])
draw_centered_text("(XAMPP Web Server)", CX, row1_y + 55, font_box_desc, COLORS['blue']['text'])
draw_centered_text("HTML5 / CSS3 / JavaScript Frontend", CX, row1_y + 82, font_small, COLORS['blue']['text'])

# --- Arrows from Row 1 splitting to Row 2 ---
split_y = row1_y + BOX_H
row2_y = split_y + GAP

# Vertical line down from center
draw.line([(CX, split_y), (CX, split_y + 30)], fill=hex_to_rgb(ARROW_COLOR), width=3)

# Horizontal split line
left_cx = CX - 280
right_cx = CX + 280
draw.line([(left_cx, split_y + 30), (right_cx, split_y + 30)], fill=hex_to_rgb(ARROW_COLOR), width=3)

# Vertical lines down to each box
draw_arrow(left_cx, split_y + 30, left_cx, row2_y)
draw_arrow(right_cx, split_y + 30, right_cx, row2_y)

# --- ROW 2: Two boxes side by side ---
# Left: Middleware
draw_rounded_rect(left_cx - SMALL_BOX_W // 2, row2_y, SMALL_BOX_W, SMALL_BOX_H, RADIUS,
                  COLORS['gray']['bg'], COLORS['gray']['border'])
draw_centered_text("PHP $_FILES", left_cx, row2_y + 15, font_box_title, COLORS['gray']['text'])
draw_centered_text("(Middleware)", left_cx, row2_y + 48, font_box_desc, COLORS['gray']['text'])
draw_centered_text("File Upload Handler & CSV Parser", left_cx, row2_y + 80, font_small, COLORS['gray']['text'])

# Right: MySQL
draw_rounded_rect(right_cx - SMALL_BOX_W // 2, row2_y, SMALL_BOX_W, SMALL_BOX_H, RADIUS,
                  COLORS['orange']['bg'], COLORS['orange']['border'])
draw_centered_text("MySQL / MariaDB", right_cx, row2_y + 15, font_box_title, COLORS['orange']['text'])
draw_centered_text("(Database)", right_cx, row2_y + 48, font_box_desc, COLORS['orange']['text'])
draw_centered_text("capstone1 Schema", right_cx, row2_y + 80, font_small, COLORS['orange']['text'])

# --- Arrow from Middleware down to Compliance Engine ---
row3_y = row2_y + SMALL_BOX_H + GAP
draw_arrow(left_cx, row2_y + SMALL_BOX_H, left_cx, row3_y)

# --- ROW 3: Compliance Engine (centered under middleware, dashed arrow to MySQL) ---
compliance_cx = left_cx
compliance_w = 480
compliance_h = 130
draw_rounded_rect(compliance_cx - compliance_w // 2, row3_y, compliance_w, compliance_h, RADIUS,
                  COLORS['green']['bg'], COLORS['green']['border'])
draw_centered_text("Localized Compliance Engine", compliance_cx, row3_y + 12, font_box_title, COLORS['green']['text'])
draw_centered_text("Rule-Based", compliance_cx, row3_y + 45, font_label, COLORS['green']['text'])
draw_centered_text("• Senior Age >= 60", compliance_cx, row3_y + 72, font_small, COLORS['green']['text'])
draw_centered_text("• Pension <= P4,000  •  Burial <= 30 days", compliance_cx, row3_y + 98, font_small, COLORS['green']['text'])

# --- Dashed two-way arrow from Compliance Engine to MySQL ---
arrow_y = row3_y + compliance_h // 2
arrow_x1 = compliance_cx + compliance_w // 2 + 10
arrow_x2 = right_cx - SMALL_BOX_W // 2 - 10
# MySQL box extends down, so we draw horizontal arrow at the compliance engine's vertical center
# But MySQL is higher, so we draw a bent connector
# Vertical line down from MySQL
mysql_bottom = row2_y + SMALL_BOX_H
draw.line([(right_cx, mysql_bottom), (right_cx, arrow_y)], fill=hex_to_rgb(ARROW_COLOR), width=2)
# Horizontal dashed double arrow
draw_horizontal_double_arrow(compliance_cx + compliance_w // 2 + 10, arrow_y, right_cx - 15)

# --- Arrow from Compliance Engine down to FSM Controller ---
row4_y = row3_y + compliance_h + GAP
draw_arrow(compliance_cx, row3_y + compliance_h, compliance_cx, row4_y)

# --- ROW 4: FSM State Controller ---
fsm_cx = CX
fsm_w = 520
fsm_h = 130
# Center FSM below compliance
draw_rounded_rect(fsm_cx - fsm_w // 2, row4_y, fsm_w, fsm_h, RADIUS,
                  COLORS['purple']['bg'], COLORS['purple']['border'])
draw_centered_text("FSM State Controller", fsm_cx, row4_y + 12, font_box_title, COLORS['purple']['text'])
draw_centered_text("(Workflow Engine)", fsm_cx, row4_y + 45, font_box_desc, COLORS['purple']['text'])

# Draw FSM states flow
states = ["Received", "For Review", "Verified", "Approved", "Released"]
state_y = row4_y + 78
total_w = fsm_w - 60
step_w = total_w // len(states)
start_x = fsm_cx - total_w // 2

for i, state in enumerate(states):
    sx = start_x + i * step_w + step_w // 2
    draw.text((sx - 25, state_y), state, fill=hex_to_rgb(COLORS['purple']['text']), font=load_font(font_paths, 13))
    if i < len(states) - 1:
        draw.text((sx + 30, state_y), "→", fill=hex_to_rgb(COLORS['purple']['text']), font=load_font(font_paths, 14))

# --- Arrow from Compliance to FSM (angled) ---
# Already drew arrow from compliance_cx down, now connect to FSM
# Draw a bent line from compliance_cx at row4_y to fsm_cx at row4_y
if compliance_cx != fsm_cx:
    mid_y = row3_y + compliance_h + GAP // 2
    draw.line([(compliance_cx, row3_y + compliance_h), (compliance_cx, mid_y)],
              fill=hex_to_rgb(ARROW_COLOR), width=3)
    draw.line([(compliance_cx, mid_y), (fsm_cx, mid_y)],
              fill=hex_to_rgb(ARROW_COLOR), width=3)
    draw_arrow(fsm_cx, mid_y, fsm_cx, row4_y)

# --- ROW 5: Application History Log ---
row5_y = row4_y + fsm_h + GAP
history_w = 540
history_h = 110
draw_arrow(fsm_cx, row4_y + fsm_h, fsm_cx, row5_y)

draw_rounded_rect(fsm_cx - history_w // 2, row5_y, history_w, history_h, RADIUS,
                  COLORS['red']['bg'], COLORS['red']['border'])
draw_centered_text("Application History Log", fsm_cx, row5_y + 15, font_box_title, COLORS['red']['text'])
draw_centered_text("& System Notifications", fsm_cx, row5_y + 48, font_box_desc, COLORS['red']['text'])
draw_centered_text("Audit Trail of All FSM State Transitions", fsm_cx, row5_y + 78, font_small, COLORS['red']['text'])

# --- ROW 6: Deployment Layer ---
row6_y = row5_y + history_h + GAP
deploy_w = 600
deploy_h = 80
draw_arrow(fsm_cx, row5_y + history_h, fsm_cx, row6_y)

draw_rounded_rect(fsm_cx - deploy_w // 2, row6_y, deploy_w, deploy_h, RADIUS,
                  COLORS['gray']['bg'], COLORS['gray']['border'])
draw_centered_text("Deployment: XAMPP Local  |  Docker  |  Render Cloud", fsm_cx, row6_y + 22, font_box_desc, COLORS['gray']['text'])
draw_centered_text("Git / GitHub Version Control", fsm_cx, row6_y + 50, font_small, COLORS['gray']['text'])

# --- Save ---
output_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "cpras_architecture.png")
img.save(output_path, "PNG", dpi=(300, 300))
print(f"Diagram saved to: {output_path}")
