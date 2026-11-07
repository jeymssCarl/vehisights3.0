from __future__ import annotations

import ast
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont


ROOT = Path(r"C:\xampp\htdocs\vehisights")
DICTIONARY_SOURCE = ROOT / "tmp" / "build_data_dictionary_docx.py"
OUTPUT = ROOT / "Updated_VehiSights_Database_ERD.png"

W, H = 5900, 3050
BG = "#ffffff"
INK = "#1f2328"
MUTED = "#4f5964"
BORDER = "#3f454c"
GRID = "#c9ced4"
ROW_ALT = "#f8f9fa"
HEADER = "#f0f1f2"
SUPPORT_HEADER = "#f4f1ed"
SHADOW = "#d9dde1"

BOX_W = 1000
HEADER_H = 54
ROW_H = 34


def get_font(size: int, bold: bool = False):
    candidates = [
        Path(r"C:\Windows\Fonts\arialbd.ttf" if bold else r"C:\Windows\Fonts\arial.ttf"),
        Path(r"C:\Windows\Fonts\segoeuib.ttf" if bold else r"C:\Windows\Fonts\segoeui.ttf"),
    ]
    for path in candidates:
        if path.exists():
            return ImageFont.truetype(str(path), size)
    return ImageFont.load_default()


TABLE_FONT = get_font(28, True)
FIELD_FONT = get_font(21)
KEY_FONT = get_font(20, True)
TYPE_FONT = get_font(20)
LEGEND_FONT = get_font(19)


def read_table_groups():
    tree = ast.parse(DICTIONARY_SOURCE.read_text(encoding="utf-8"))
    values = {}
    for node in tree.body:
        if not isinstance(node, ast.Assign):
            continue
        for target in node.targets:
            if isinstance(target, ast.Name) and target.id in {"tables", "support_tables"}:
                values[target.id] = ast.literal_eval(node.value)
    return values["tables"] + values["support_tables"]


TABLES = {table_name: fields for _, table_name, _, fields in read_table_groups()}

PRIMARY_KEYS = {name: {"id"} for name in TABLES}
PRIMARY_KEYS["password_reset_tokens"] = {"email"}

FOREIGN_KEYS = {
    "social_accounts": {"user_id"},
    "email_verification_codes": {"user_id"},
    "cameras": {"created_by"},
    "sessions": {"created_by"},
    "manual_counts": {"session_id", "created_by"},
    "session_details": {"session_id"},
    "schedules": {"camera_id", "created_by"},
    "auto_sessions": {"created_by", "schedule_id", "camera_id"},
    "auto_session_details": {"auto_session_id"},
    "schedule_logs": {"schedule_id", "auto_session_id"},
    "traffic_history": {"camera_id"},
    "predictions": {"camera_id"},
}

UNIQUE_KEYS = {
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

SUPPORT_TABLES = {"password_reset_tokens", "failed_jobs", "migrations"}

TYPE_SHORT = {
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


def short_type(value: str) -> str:
    return TYPE_SHORT.get(value, value)


def box_height(table: str) -> int:
    return HEADER_H + len(TABLES[table]) * ROW_H + 8


# The layout follows the sparse, balanced style of the supplied reference.
POS = {
    "social_accounts": (70, 70),
    "email_verification_codes": (70, 470),
    "personal_access_tokens": (70, 1040),
    "password_reset_tokens": (70, 1690),

    "users": (1180, 70),
    "sessions": (1180, 690),
    "manual_counts": (1180, 1320),
    "session_details": (1180, 2350),

    "cameras": (2290, 70),
    "traffic_history": (2290, 650),
    "predictions": (2290, 1260),

    "schedules": (3400, 70),
    "auto_sessions": (3400, 900),
    "auto_session_details": (3400, 1840),
    "schedule_logs": (3400, 2390),

    "failed_jobs": (4510, 450),
    "migrations": (4510, 1080),
}


# parent, child, parent field, child FK, parent-end marker, child-end marker
# Parent-end marker describes how many parents one child may reference.
# Child-end marker describes how many children one parent may have.
RELATIONSHIPS = [
    ("users", "social_accounts", "id", "user_id", "one", "many_optional"),
    ("users", "email_verification_codes", "id", "user_id", "one", "one_optional"),
    ("users", "cameras", "id", "created_by", "one", "many_optional"),
    ("users", "sessions", "id", "created_by", "one", "many_optional"),
    ("users", "manual_counts", "id", "created_by", "one", "many_optional"),
    ("users", "schedules", "id", "created_by", "one", "many_optional"),
    ("users", "auto_sessions", "id", "created_by", "one", "many_optional"),
    ("sessions", "manual_counts", "id", "session_id", "one_optional", "one_optional"),
    ("sessions", "session_details", "id", "session_id", "one", "one_optional"),
    ("cameras", "schedules", "id", "camera_id", "one", "many_optional"),
    ("cameras", "auto_sessions", "id", "camera_id", "one_optional", "many_optional"),
    ("cameras", "traffic_history", "id", "camera_id", "one_optional", "many_optional"),
    ("cameras", "predictions", "id", "camera_id", "one_optional", "many_optional"),
    ("schedules", "auto_sessions", "id", "schedule_id", "one_optional", "many_optional"),
    ("schedules", "schedule_logs", "id", "schedule_id", "one", "many_optional"),
    ("auto_sessions", "auto_session_details", "id", "auto_session_id", "one", "one_optional"),
    ("auto_sessions", "schedule_logs", "id", "auto_session_id", "one_optional", "many_optional"),
]


def field_center_y(table: str, field: str) -> int:
    names = [row[0] for row in TABLES[table]]
    index = names.index(field)
    return POS[table][1] + HEADER_H + index * ROW_H + ROW_H // 2


def side_anchor(table: str, field: str, side: str):
    x, _ = POS[table]
    y = field_center_y(table, field)
    return (x if side == "left" else x + BOX_W, y)


def relationship_sides(parent: str, child: str):
    px, _ = POS[parent]
    cx, _ = POS[child]
    if px < cx:
        return "right", "left"
    if px > cx:
        return "left", "right"
    return "right", "right"


img = Image.new("RGB", (W, H), BG)
draw = ImageDraw.Draw(img)


def draw_marker(point, side: str, marker: str):
    """Draw a horizontal Crow's Foot marker adjacent to an entity edge."""
    x, y = point
    outward = 1 if side == "right" else -1

    def px(distance):
        return x + outward * distance

    if marker == "one":
        for distance in (12, 24):
            xx = px(distance)
            draw.line((xx, y - 11, xx, y + 11), fill=INK, width=3)
    elif marker == "one_optional":
        center = px(14)
        draw.ellipse((center - 8, y - 8, center + 8, y + 8), fill=BG, outline=INK, width=3)
        xx = px(32)
        draw.line((xx, y - 11, xx, y + 11), fill=INK, width=3)
    elif marker == "many_optional":
        center = px(14)
        draw.ellipse((center - 8, y - 8, center + 8, y + 8), fill=BG, outline=INK, width=3)
        joint = px(30)
        tip = px(46)
        draw.line((joint, y, tip, y), fill=INK, width=3)
        draw.line((joint, y, tip, y - 14), fill=INK, width=3)
        draw.line((joint, y, tip, y + 14), fill=INK, width=3)


channel_use = {}


def draw_relationship(parent, child, parent_field, child_field, parent_marker, child_marker):
    parent_side, child_side = relationship_sides(parent, child)
    p = side_anchor(parent, parent_field, parent_side)
    c = side_anchor(child, child_field, child_side)

    key = tuple(sorted((POS[parent][0], POS[child][0])))
    channel_index = channel_use.get(key, 0)
    channel_use[key] = channel_index + 1

    p_start = (p[0] + (52 if parent_side == "right" else -52), p[1])
    c_start = (c[0] + (52 if child_side == "right" else -52), c[1])

    if parent_side != child_side:
        mid_x = (p_start[0] + c_start[0]) // 2 + (channel_index - 2) * 18
        path = [p_start, (mid_x, p_start[1]), (mid_x, c_start[1]), c_start]
    else:
        route_x = max(p_start[0], c_start[0]) + 80 + channel_index * 22
        path = [p_start, (route_x, p_start[1]), (route_x, c_start[1]), c_start]

    draw.line(path, fill=INK, width=3, joint="curve")
    draw.line((p, p_start), fill=INK, width=3)
    draw.line((c, c_start), fill=INK, width=3)
    draw_marker(p, parent_side, parent_marker)
    draw_marker(c, child_side, child_marker)


# Relationships first, so table cards stay visually dominant.
for relationship in RELATIONSHIPS:
    draw_relationship(*relationship)


def draw_entity(table: str):
    x, y = POS[table]
    h = box_height(table)
    draw.rounded_rectangle((x + 9, y + 10, x + BOX_W + 9, y + h + 10), radius=12, fill=SHADOW)
    draw.rounded_rectangle((x, y, x + BOX_W, y + h), radius=12, fill=BG, outline=BORDER, width=3)

    header_fill = SUPPORT_HEADER if table in SUPPORT_TABLES else HEADER
    draw.rounded_rectangle((x, y, x + BOX_W, y + HEADER_H), radius=12, fill=header_fill, outline=BORDER, width=3)
    draw.rectangle((x + 2, y + HEADER_H - 12, x + BOX_W - 2, y + HEADER_H + 1), fill=header_fill)
    draw.line((x, y + HEADER_H, x + BOX_W, y + HEADER_H), fill=BORDER, width=3)
    draw.text((x + 18, y + 11), table, font=TABLE_FONT, fill=INK)

    key_x = x + 650
    type_x = x + 755
    draw.line((key_x - 10, y + HEADER_H, key_x - 10, y + h), fill=GRID, width=1)
    draw.line((type_x - 10, y + HEADER_H, type_x - 10, y + h), fill=GRID, width=1)

    for index, (field, data_type, _) in enumerate(TABLES[table]):
        top = y + HEADER_H + index * ROW_H
        if index % 2:
            draw.rectangle((x + 2, top, x + BOX_W - 2, top + ROW_H), fill=ROW_ALT)
        draw.line((x, top + ROW_H, x + BOX_W, top + ROW_H), fill=GRID, width=1)

        keys = []
        if field in PRIMARY_KEYS.get(table, set()):
            keys.append("PK")
        if field in FOREIGN_KEYS.get(table, set()):
            keys.append("FK")
        if field in UNIQUE_KEYS.get(table, set()):
            keys.append("UK")

        draw.text((x + 16, top + 6), field, font=FIELD_FONT, fill=INK)
        draw.text((key_x, top + 6), "/".join(keys), font=KEY_FONT, fill=INK)
        draw.text((type_x, top + 6), short_type(data_type), font=TYPE_FONT, fill=MUTED)


for table in POS:
    draw_entity(table)


# Minimal footer legend. Standalone support tables intentionally have no connectors.
footer_y = 2975
draw.line((70, footer_y - 22, W - 70, footer_y - 22), fill=GRID, width=2)
draw.text(
    (70, footer_y),
    "PK  Primary Key     FK  Foreign Key     UK  Unique Key     ?  Nullable     ○|  Zero or one     ○<  Zero or many",
    font=LEGEND_FONT,
    fill=MUTED,
)
draw.text((4560, footer_y), "Beige headers: Laravel support tables", font=LEGEND_FONT, fill=MUTED)

img.save(OUTPUT, format="PNG", optimize=True, dpi=(220, 220))
print(OUTPUT)
