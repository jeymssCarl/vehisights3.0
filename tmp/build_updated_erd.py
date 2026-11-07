from __future__ import annotations

import ast
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont


ROOT = Path(r"C:\xampp\htdocs\vehisights")
SOURCE = ROOT / "tmp" / "build_data_dictionary_docx.py"
OUTPUT = ROOT / "Vehisights_Updated_ERD.png"

CANVAS_W = 6200
CANVAS_H = 4300
SCALE = 1

BG = "#f7f8fa"
PANEL = "#ffffff"
HEADER = "#e8ebef"
HEADER_SUPPORT = "#f1eee9"
OUTLINE = "#4c5561"
GRID = "#c7cdd4"
TEXT = "#18202a"
MUTED = "#59636f"
REL = "#66717d"
ACCENT = "#8f1d1d"


def font(size: int, bold: bool = False):
    paths = [
        Path(r"C:\Windows\Fonts\arialbd.ttf" if bold else r"C:\Windows\Fonts\arial.ttf"),
        Path(r"C:\Windows\Fonts\segoeuib.ttf" if bold else r"C:\Windows\Fonts\segoeui.ttf"),
    ]
    for path in paths:
        if path.exists():
            return ImageFont.truetype(str(path), size)
    return ImageFont.load_default()


TITLE_FONT = font(62, True)
SUBTITLE_FONT = font(28)
TABLE_FONT = font(28, True)
FIELD_FONT = font(22, True)
BODY_FONT = font(21)
LEGEND_FONT = font(21)
GROUP_FONT = font(25, True)
CARDINALITY_FONT = font(20, True)


def load_tables():
    tree = ast.parse(SOURCE.read_text(encoding="utf-8"))
    groups = {}
    for node in tree.body:
        if not isinstance(node, ast.Assign):
            continue
        for target in node.targets:
            if isinstance(target, ast.Name) and target.id in {"tables", "support_tables"}:
                groups[target.id] = ast.literal_eval(node.value)
    if "tables" not in groups or "support_tables" not in groups:
        raise RuntimeError("Could not find all table data in the dictionary builder.")
    return groups["tables"] + groups["support_tables"]


TABLES = {table_name: fields for _, table_name, _, fields in load_tables()}

PK = {name: {"id"} for name in TABLES}
PK["password_reset_tokens"] = {"email"}

FK = {
    "social_accounts": {"user_id"},
    "email_verification_codes": {"user_id"},
    "cameras": {"created_by"},
    "sessions": {"created_by"},
    "manual_counts": {"session_id", "created_by"},
    "session_details": {"session_id"},
    "schedules": {"camera_id", "created_by"},
    "schedule_logs": {"schedule_id", "auto_session_id"},
    "auto_sessions": {"schedule_id", "camera_id", "created_by"},
    "auto_session_details": {"auto_session_id"},
    "traffic_history": {"camera_id"},
    "predictions": {"camera_id"},
}

UK = {
    "users": {"email"},
    "social_accounts": {"provider_user_id"},
    "email_verification_codes": {"user_id"},
    "personal_access_tokens": {"token"},
    "cameras": {"ip_address"},
    "sessions": {"session_code"},
    "manual_counts": {"session_id"},
    "session_details": {"session_id"},
    "auto_sessions": {"session_code"},
    "auto_session_details": {"auto_session_id"},
    "failed_jobs": {"uuid"},
}

SUPPORT = {"password_reset_tokens", "failed_jobs", "migrations"}

DISPLAY_TYPES = {
    "timestamp, nullable": "timestamp?",
    "varchar(255), nullable": "varchar(255)?",
    "varchar(100), nullable": "varchar(100)?",
    "varchar(20), nullable": "varchar(20)?",
    "bigint, nullable": "bigint?",
    "date, nullable": "date?",
    "time, nullable": "time?",
    "text, nullable": "text?",
    "json, nullable": "json?",
    "integer, nullable": "integer?",
    "boolean, nullable": "boolean?",
    "numeric(10,2), nullable": "numeric(10,2)?",
}


def compact_type(value: str) -> str:
    return DISPLAY_TYPES.get(value, value)


ROW_H = 36
HEADER_H = 58
BOX_W = 1060


def box_height(table: str) -> int:
    return HEADER_H + ROW_H * len(TABLES[table]) + 18


# Five lanes keep the diagram readable and minimize crossing relationships.
X = [100, 1320, 2540, 3760, 4980]

positions = {
    "users": (X[0], 360),
    "social_accounts": (X[0], 1080),
    "email_verification_codes": (X[0], 1490),
    "personal_access_tokens": (X[0], 2040),

    "sessions": (X[1], 520),
    "manual_counts": (X[1], 1250),
    "session_details": (X[1], 2260),

    "cameras": (X[2], 360),
    "traffic_history": (X[2], 1050),
    "predictions": (X[2], 1760),

    "schedules": (X[3], 360),
    "auto_sessions": (X[3], 1290),
    "auto_session_details": (X[3], 2450),
    "schedule_logs": (X[3], 3070),

    "password_reset_tokens": (X[4], 560),
    "failed_jobs": (X[4], 1040),
    "migrations": (X[4], 1580),
}


RELATIONS = [
    ("users", "social_accounts", "id", "user_id"),
    ("users", "email_verification_codes", "id", "user_id"),
    ("users", "cameras", "id", "created_by"),
    ("users", "sessions", "id", "created_by"),
    ("users", "manual_counts", "id", "created_by"),
    ("users", "schedules", "id", "created_by"),
    ("users", "auto_sessions", "id", "created_by"),
    ("sessions", "manual_counts", "id", "session_id"),
    ("sessions", "session_details", "id", "session_id"),
    ("cameras", "schedules", "id", "camera_id"),
    ("cameras", "auto_sessions", "id", "camera_id"),
    ("cameras", "traffic_history", "id", "camera_id"),
    ("cameras", "predictions", "id", "camera_id"),
    ("schedules", "auto_sessions", "id", "schedule_id"),
    ("schedules", "schedule_logs", "id", "schedule_id"),
    ("auto_sessions", "auto_session_details", "id", "auto_session_id"),
    ("auto_sessions", "schedule_logs", "id", "auto_session_id"),
]


def field_y(table: str, field: str) -> int:
    fields = [item[0] for item in TABLES[table]]
    idx = fields.index(field)
    return positions[table][1] + HEADER_H + idx * ROW_H + ROW_H // 2


def anchor(table: str, field: str, side: str):
    x, _ = positions[table]
    return (x if side == "left" else x + BOX_W, field_y(table, field))


def choose_sides(parent: str, child: str):
    px, py = positions[parent]
    cx, cy = positions[child]
    if cx > px:
        return "right", "left"
    if cx < px:
        return "left", "right"
    return ("right", "right") if cy > py else ("left", "left")


img = Image.new("RGB", (CANVAS_W, CANVAS_H), BG)
draw = ImageDraw.Draw(img)

# Header
draw.text((100, 70), "VEHISIGHTS UPDATED ENTITY–RELATIONSHIP DIAGRAM", font=TITLE_FONT, fill=TEXT)
draw.text(
    (103, 151),
    "Current Supabase PostgreSQL schema • November 5, 2026",
    font=SUBTITLE_FONT,
    fill=MUTED,
)
draw.line((100, 215, 6100, 215), fill=ACCENT, width=5)

# Lane labels
lane_labels = [
    (X[0], "IDENTITY & ACCESS"),
    (X[1], "MANUAL COUNTING"),
    (X[2], "CAMERAS & ANALYTICS"),
    (X[3], "SCHEDULING & AUTO COUNTING"),
    (X[4], "LARAVEL SUPPORT TABLES"),
]
for x, label in lane_labels:
    draw.text((x, 285), label, font=GROUP_FONT, fill=ACCENT if x != X[4] else MUTED)

# Relationship lines are rendered behind entity boxes.
for index, (parent, child, parent_field, child_field) in enumerate(RELATIONS):
    parent_side, child_side = choose_sides(parent, child)
    p1 = anchor(parent, parent_field, parent_side)
    p2 = anchor(child, child_field, child_side)

    # Spread parallel routes slightly to keep individual connections visible.
    offset = 18 * ((index % 5) - 2)
    if parent_side != child_side:
        mid_x = (p1[0] + p2[0]) // 2 + offset
        points = [p1, (mid_x, p1[1]), (mid_x, p2[1]), p2]
    else:
        route_x = min(p1[0], p2[0]) - 55 - abs(offset) if parent_side == "left" else max(p1[0], p2[0]) + 55 + abs(offset)
        points = [p1, (route_x, p1[1]), (route_x, p2[1]), p2]

    draw.line(points, fill=REL, width=4, joint="curve")
    # One-to-many cardinality, matching the compact notation of the reference.
    draw.ellipse((p1[0] - 8, p1[1] - 8, p1[0] + 8, p1[1] + 8), fill=PANEL, outline=REL, width=3)
    draw.text((p1[0] + (10 if parent_side == "right" else -28), p1[1] - 25), "1", font=CARDINALITY_FONT, fill=REL)
    draw.ellipse((p2[0] - 8, p2[1] - 8, p2[0] + 8, p2[1] + 8), fill=PANEL, outline=REL, width=3)
    draw.text((p2[0] + (10 if child_side == "right" else -30), p2[1] - 25), "N", font=CARDINALITY_FONT, fill=REL)


def draw_table(table: str):
    x, y = positions[table]
    h = box_height(table)

    # Shadow and frame
    draw.rounded_rectangle((x + 10, y + 12, x + BOX_W + 10, y + h + 12), radius=13, fill="#dde1e6")
    draw.rounded_rectangle((x, y, x + BOX_W, y + h), radius=13, fill=PANEL, outline=OUTLINE, width=3)
    header_color = HEADER_SUPPORT if table in SUPPORT else HEADER
    draw.rounded_rectangle((x, y, x + BOX_W, y + HEADER_H), radius=13, fill=header_color, outline=OUTLINE, width=3)
    draw.rectangle((x + 2, y + HEADER_H - 13, x + BOX_W - 2, y + HEADER_H + 2), fill=header_color)
    draw.line((x, y + HEADER_H, x + BOX_W, y + HEADER_H), fill=OUTLINE, width=3)
    draw.text((x + 20, y + 13), table, font=TABLE_FONT, fill=TEXT)

    key_x = x + 675
    type_x = x + 780
    draw.line((key_x - 12, y + HEADER_H, key_x - 12, y + h), fill=GRID, width=2)
    draw.line((type_x - 12, y + HEADER_H, type_x - 12, y + h), fill=GRID, width=2)

    for idx, (field_name, data_type, _) in enumerate(TABLES[table]):
        row_y = y + HEADER_H + idx * ROW_H
        if idx % 2:
            draw.rectangle((x + 2, row_y, x + BOX_W - 2, row_y + ROW_H), fill="#fafbfc")
        draw.line((x, row_y + ROW_H, x + BOX_W, row_y + ROW_H), fill=GRID, width=1)

        tags = []
        if field_name in PK.get(table, set()):
            tags.append("PK")
        if field_name in FK.get(table, set()):
            tags.append("FK")
        if field_name in UK.get(table, set()):
            tags.append("UK")
        draw.text((x + 18, row_y + 7), field_name, font=FIELD_FONT if tags else BODY_FONT, fill=TEXT)
        draw.text((key_x, row_y + 7), "/".join(tags), font=FIELD_FONT, fill=ACCENT if tags else MUTED)
        draw.text((type_x, row_y + 7), compact_type(data_type), font=BODY_FONT, fill=MUTED)


for name in positions:
    draw_table(name)

# Footer legend and document note.
legend_y = 4200
draw.line((100, legend_y - 30, 6100, legend_y - 30), fill=GRID, width=2)
draw.text((100, legend_y), "KEYS:", font=LEGEND_FONT, fill=TEXT)
draw.text((185, legend_y), "PK = Primary Key    FK = Foreign Key    UK = Unique Key    ? = Nullable", font=LEGEND_FONT, fill=MUTED)
draw.text((4380, legend_y), "Laravel support tables are shaded beige.", font=LEGEND_FONT, fill=MUTED)

img.save(OUTPUT, format="PNG", optimize=True, dpi=(220, 220))
print(OUTPUT)
