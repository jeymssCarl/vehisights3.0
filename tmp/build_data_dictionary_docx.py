from pathlib import Path

from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor


OUTPUT = Path(r"C:\xampp\htdocs\vehisights\tmp\Vehisights_Updated_Data_Dictionary_raw.docx")


tables = [
    ("Users", "users", "Stores local and Google-authenticated user accounts.", [
        ("id", "bigint", "Unique identifier of the user."),
        ("name", "varchar(255), nullable", "Complete display name of the user."),
        ("email", "varchar(255)", "Unique email address used for authentication."),
        ("email_verified_at", "timestamp, nullable", "Date and time the email address was verified."),
        ("password", "varchar(255), nullable", "Hashed password; may be null for Google-only accounts."),
        ("remember_token", "varchar(100), nullable", "Token used by Laravel's Remember Me authentication."),
        ("department", "varchar(255)", "Department or office of the user; defaults to City Planning."),
        ("is_active", "boolean", "Indicates whether the account is allowed to access the system."),
        ("role", "varchar(20), nullable", "Access role such as viewer, analyst, admin, or super_admin."),
        ("first_name", "varchar(255), nullable", "First name of the user."),
        ("last_name", "varchar(255), nullable", "Last name of the user."),
        ("google_verified_at", "timestamp, nullable", "Date and time Google account verification was completed."),
        ("created_at", "timestamp, nullable", "Date and time the account was created."),
        ("updated_at", "timestamp, nullable", "Date and time the account was last updated."),
    ]),
    ("Social Accounts", "social_accounts", "Connects a system user to an external authentication provider.", [
        ("id", "bigint", "Unique identifier of the social account record."),
        ("user_id", "bigint", "References users.id; deleted with the parent user."),
        ("provider", "varchar(32)", "Authentication provider, such as google."),
        ("provider_user_id", "varchar(255)", "Unique account identifier supplied by the provider."),
        ("created_at", "timestamp, nullable", "Date and time the connection was created."),
        ("updated_at", "timestamp, nullable", "Date and time the connection was updated."),
    ]),
    ("Email Verification Codes", "email_verification_codes", "Stores temporary verification codes for Google-authenticated accounts.", [
        ("id", "bigint", "Unique identifier of the verification record."),
        ("user_id", "bigint", "Unique reference to users.id; deleted with the parent user."),
        ("code_hash", "varchar(255)", "Secure hash of the verification code."),
        ("expires_at", "timestamp", "Date and time the verification code expires."),
        ("attempts", "smallint", "Number of unsuccessful verification attempts."),
        ("last_sent_at", "timestamp", "Date and time the most recent code was sent."),
        ("created_at", "timestamp, nullable", "Date and time the record was created."),
        ("updated_at", "timestamp, nullable", "Date and time the record was updated."),
    ]),
    ("Personal Access Tokens", "personal_access_tokens", "Stores Laravel Sanctum API tokens used by external applications such as TrafficCounter for macOS.", [
        ("id", "bigint", "Unique identifier of the API token."),
        ("tokenable_type", "varchar(255)", "Model type that owns the token."),
        ("tokenable_id", "bigint", "Identifier of the user or model that owns the token."),
        ("name", "varchar(255)", "Descriptive name of the token or client device."),
        ("token", "varchar(64)", "Securely hashed API token."),
        ("abilities", "text, nullable", "Permissions assigned to the token."),
        ("last_used_at", "timestamp, nullable", "Date and time the token was last used."),
        ("expires_at", "timestamp, nullable", "Optional expiration date and time."),
        ("created_at", "timestamp, nullable", "Date and time the token was created."),
        ("updated_at", "timestamp, nullable", "Date and time the token was updated."),
    ]),
    ("Cameras", "cameras", "Stores cameras registered for scheduled automatic vehicle counting.", [
        ("id", "bigint", "Unique identifier of the camera."),
        ("name", "varchar(255)", "Descriptive camera name."),
        ("ip_address", "varchar(255)", "Unique IP address of the camera."),
        ("location", "varchar(255)", "Physical location of the camera."),
        ("stream_url", "varchar(255), nullable", "Optional RTSP or HTTP video stream URL."),
        ("status", "varchar(255)", "Operational status, normally online or offline."),
        ("last_detection_at", "timestamp, nullable", "Date and time of the most recent vehicle detection."),
        ("detection_sensitivity", "integer", "Detection sensitivity setting; defaults to 50."),
        ("roi", "json, nullable", "Region-of-interest configuration for vehicle detection."),
        ("created_by", "bigint", "References users.id; user who registered the camera."),
        ("created_at", "timestamp, nullable", "Date and time the camera was registered."),
        ("updated_at", "timestamp, nullable", "Date and time the camera was updated."),
    ]),
    ("Sessions", "sessions", "Stores the main record for a manually counted traffic session.", [
        ("id", "bigint", "Unique identifier of the manual session."),
        ("session_code", "varchar(255)", "Unique code assigned to the session."),
        ("road_name", "varchar(255)", "Road or station being monitored."),
        ("barangay", "varchar(255), nullable", "Barangay where the road is located."),
        ("city", "varchar(255)", "City or municipality where the road is located."),
        ("direction", "varchar(255)", "Direction of traffic flow."),
        ("lanes", "integer", "Number of lanes included in the count."),
        ("has_median", "boolean", "Indicates whether the road has a median divider."),
        ("base_capacity_per_lane", "integer", "Road capacity per lane in PCU per hour."),
        ("start_time", "timestamp", "Date and time the manual session started."),
        ("end_time", "timestamp, nullable", "Date and time the manual session ended."),
        ("status", "varchar(255)", "Session state, such as active or ended."),
        ("created_by", "bigint", "References users.id; user who performed the count."),
        ("created_at", "timestamp, nullable", "Date and time the session was created."),
        ("updated_at", "timestamp, nullable", "Date and time the session was updated."),
    ]),
    ("Manual Counts", "manual_counts", "Stores vehicle counts and calculated results for manual counting sessions.", [
        ("id", "bigint", "Unique identifier of the manual count record."),
        ("session_id", "bigint, nullable", "Unique reference to sessions.id; deleted with the parent session."),
        ("road_name", "varchar(255)", "Road or station where the count was conducted."),
        ("barangay", "varchar(255), nullable", "Barangay where the road is located."),
        ("city", "varchar(255)", "City or municipality where the road is located."),
        ("direction_from", "varchar(255), nullable", "Starting point of traffic movement."),
        ("direction_to", "varchar(255), nullable", "Destination of traffic movement."),
        ("count_date", "date", "Date the traffic count was conducted."),
        ("hour_start", "time", "Starting time of the count interval."),
        ("hour_end", "time", "Ending time of the count interval."),
        ("has_median", "boolean", "Indicates whether the road has a median."),
        ("lanes", "integer", "Number of lanes included in the count."),
        ("counts", "json", "Vehicle counts for the eight vehicle classifications."),
        ("pcu_totals", "json", "PCU-adjusted totals for each vehicle classification."),
        ("total_pcu", "double precision", "Combined Passenger Car Unit total."),
        ("vc_ratio", "double precision", "Computed volume-to-capacity ratio."),
        ("los", "char(1)", "Level of Service classification from A to F."),
        ("created_by", "bigint", "References users.id; user who recorded the count."),
        ("created_at", "timestamp, nullable", "Date and time the count was saved."),
        ("updated_at", "timestamp, nullable", "Date and time the count was updated."),
    ]),
    ("Session Details", "session_details", "Legacy detail table retained for compatibility with older manual session records.", [
        ("id", "bigint", "Unique identifier of the detail record."),
        ("session_id", "bigint", "Unique reference to sessions.id; deleted with the parent session."),
        ("total_pcu", "numeric(10,2)", "Total Passenger Car Unit volume."),
        ("vc_ratio", "numeric(5,2)", "Volume-to-capacity ratio."),
        ("los", "char(1)", "Level of Service classification from A to F."),
        ("counts", "json", "Vehicle counts per classification."),
        ("pcu_totals", "json", "PCU-adjusted values per vehicle classification."),
        ("source", "varchar(20)", "Source of the count; defaults to manual."),
        ("created_at", "timestamp, nullable", "Date and time the record was created."),
        ("updated_at", "timestamp, nullable", "Date and time the record was updated."),
    ]),
    ("Schedules", "schedules", "Stores configurations for scheduled automatic vehicle counting.", [
        ("id", "bigint", "Unique identifier of the schedule."),
        ("schedule_name", "varchar(255)", "Descriptive name of the scheduled count."),
        ("camera_id", "bigint", "References cameras.id."),
        ("start_date", "date", "Date the schedule begins."),
        ("end_date", "date, nullable", "Optional date the schedule ends."),
        ("start_time", "time", "Daily start time of automatic counting."),
        ("end_time", "time", "Daily end time of automatic counting."),
        ("recurrence", "varchar(255)", "Recurrence mode such as once, daily, weekly, or monthly."),
        ("recurrence_days", "json, nullable", "Selected days used by recurring schedules."),
        ("is_active", "boolean", "Indicates whether the schedule is enabled."),
        ("created_by", "bigint", "References users.id; user who created the schedule."),
        ("created_at", "timestamp, nullable", "Date and time the schedule was created."),
        ("updated_at", "timestamp, nullable", "Date and time the schedule was updated."),
        ("road_name", "varchar(255), nullable", "Road or station monitored by the schedule."),
        ("barangay", "varchar(255), nullable", "Barangay where the camera is assigned."),
        ("city", "varchar(255), nullable", "City or municipality of the scheduled count."),
        ("direction_from", "varchar(255), nullable", "Starting direction of traffic flow."),
        ("direction_to", "varchar(255), nullable", "Destination direction of traffic flow."),
        ("lanes", "integer, nullable", "Number of lanes monitored."),
        ("has_median", "boolean, nullable", "Indicates whether the road has a median."),
        ("base_capacity_per_lane", "integer, nullable", "Capacity of each lane in PCU per hour."),
    ]),
    ("Schedule Logs", "schedule_logs", "Stores the execution history of automatic counting schedules.", [
        ("id", "bigint", "Unique identifier of the schedule log."),
        ("schedule_id", "bigint", "References schedules.id; deleted with the parent schedule."),
        ("auto_session_id", "bigint, nullable", "References auto_sessions.id; set to null if that session is deleted."),
        ("status", "varchar(255)", "Execution state such as pending, running, success, or failed."),
        ("error_message", "text, nullable", "Error details when schedule execution fails."),
        ("started_at", "timestamp, nullable", "Date and time automatic execution started."),
        ("completed_at", "timestamp, nullable", "Date and time automatic execution completed."),
        ("created_at", "timestamp, nullable", "Date and time the log was created."),
        ("updated_at", "timestamp, nullable", "Date and time the log was updated."),
    ]),
    ("Auto Sessions", "auto_sessions", "Stores the main record and configuration snapshot for an automatic camera counting session.", [
        ("id", "bigint", "Unique identifier of the automatic session."),
        ("session_code", "varchar(255)", "Unique automatic session code."),
        ("camera_name", "varchar(255)", "Snapshot of the camera name during the session."),
        ("ip_address", "varchar(255)", "Snapshot of the camera IP address."),
        ("location", "varchar(255)", "Snapshot of the camera location."),
        ("start_time", "timestamp", "Date and time automatic counting started."),
        ("end_time", "timestamp, nullable", "Date and time automatic counting ended."),
        ("status", "varchar(255)", "Session state such as active or ended."),
        ("created_by", "bigint", "References users.id; user responsible for the session."),
        ("created_at", "timestamp, nullable", "Date and time the session was created."),
        ("updated_at", "timestamp, nullable", "Date and time the session was updated."),
        ("schedule_id", "bigint, nullable", "References schedules.id; set to null if the schedule is deleted."),
        ("camera_id", "bigint, nullable", "References cameras.id; set to null if the camera is deleted."),
        ("road_name", "varchar(255), nullable", "Road or station monitored during the session."),
        ("barangay", "varchar(255), nullable", "Barangay of the monitored road."),
        ("city", "varchar(255), nullable", "City or municipality of the monitored road."),
        ("direction", "varchar(255), nullable", "Direction of traffic flow."),
        ("lanes", "integer, nullable", "Number of lanes monitored."),
        ("has_median", "boolean, nullable", "Indicates whether the road has a median."),
        ("base_capacity_per_lane", "integer, nullable", "Road capacity per lane in PCU per hour."),
    ]),
    ("Auto Session Details", "auto_session_details", "Stores the vehicle counts and calculated results of an automatic session.", [
        ("id", "bigint", "Unique identifier of the automatic detail record."),
        ("auto_session_id", "bigint", "Unique reference to auto_sessions.id; deleted with the parent session."),
        ("total_pcu", "numeric(10,2)", "Total Passenger Car Unit volume."),
        ("vc_ratio", "numeric(5,2)", "Computed volume-to-capacity ratio."),
        ("los", "char(1)", "Level of Service classification from A to F."),
        ("counts", "json", "Automatically detected counts per vehicle classification."),
        ("pcu_totals", "json", "PCU-adjusted total per vehicle classification."),
        ("source", "varchar(20)", "Source identifier; defaults to auto."),
        ("created_at", "timestamp, nullable", "Date and time the result was created."),
        ("updated_at", "timestamp, nullable", "Date and time the result was updated."),
    ]),
    ("Traffic History", "traffic_history", "Stores normalized historical traffic data for dashboards, reports, and prediction analysis.", [
        ("id", "bigint", "Unique identifier of the historical record."),
        ("camera_id", "bigint, nullable", "References cameras.id; set to null if the camera is deleted."),
        ("road_name", "varchar(255)", "Road or station represented by the record."),
        ("direction", "varchar(255), nullable", "Traffic flow direction."),
        ("hour_timestamp", "timestamp", "Date and time represented by the traffic record."),
        ("total_pcu", "numeric(10,2)", "Total Passenger Car Unit volume."),
        ("vc_ratio", "numeric(5,2)", "Volume-to-capacity ratio."),
        ("los", "char(1)", "Level of Service classification from A to F."),
        ("counts", "json", "Vehicle counts per classification."),
        ("source", "varchar(255)", "Record source, either manual or auto."),
        ("created_at", "timestamp, nullable", "Date and time the history record was created."),
        ("updated_at", "timestamp, nullable", "Date and time the record was updated."),
    ]),
    ("Predictions", "predictions", "Stores generated traffic predictions and comparison values from observed traffic data.", [
        ("id", "bigint", "Unique identifier of the prediction."),
        ("camera_id", "bigint, nullable", "References cameras.id when the prediction is camera-specific."),
        ("road_name", "varchar(255), nullable", "Road or station covered by the prediction."),
        ("direction", "varchar(255), nullable", "Traffic direction represented by the prediction."),
        ("prediction_type", "varchar(255)", "Prediction category or model type."),
        ("prediction_date", "date", "Date for which the prediction applies."),
        ("prediction_hour", "integer, nullable", "Hour of the day represented by the prediction."),
        ("predicted_pcu", "numeric(10,2)", "Predicted Passenger Car Unit volume."),
        ("predicted_vc_ratio", "numeric(5,2)", "Predicted volume-to-capacity ratio."),
        ("predicted_los", "char(1)", "Predicted Level of Service from A to F."),
        ("confidence_score", "numeric(5,2)", "Confidence score assigned to the prediction."),
        ("actual_pcu", "numeric(10,2), nullable", "Actual PCU recorded after the predicted period."),
        ("model_version", "varchar(255)", "Version of the prediction model."),
        ("created_at", "timestamp, nullable", "Date and time the prediction was generated."),
        ("updated_at", "timestamp, nullable", "Date and time the prediction was updated."),
    ]),
]


support_tables = [
    ("Password Reset Tokens", "password_reset_tokens", "Stores temporary password-reset credentials.", [
        ("email", "varchar(255)", "Email address requesting a password reset."),
        ("token", "varchar(255)", "Secure password-reset token."),
        ("created_at", "timestamp, nullable", "Date and time the token was generated."),
    ]),
    ("Failed Jobs", "failed_jobs", "Stores background queue jobs that could not be completed.", [
        ("id", "bigint", "Unique identifier of the failed background job."),
        ("uuid", "varchar(255)", "Unique job identifier."),
        ("connection", "text", "Queue connection used by the job."),
        ("queue", "text", "Queue where the job was processed."),
        ("payload", "text", "Serialized job data."),
        ("exception", "text", "Exception generated by the failed job."),
        ("failed_at", "timestamp", "Date and time the job failed."),
    ]),
    ("Migrations", "migrations", "Tracks the database migrations applied by Laravel.", [
        ("id", "integer", "Unique migration record identifier."),
        ("migration", "varchar(255)", "Name of the executed migration."),
        ("batch", "integer", "Migration execution batch number."),
    ]),
]


def set_cell_shading(cell, fill):
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = tc_pr.find(qn("w:shd"))
    if shd is None:
        shd = OxmlElement("w:shd")
        tc_pr.append(shd)
    shd.set(qn("w:fill"), fill)


def set_cell_margins(cell, top=90, start=100, bottom=90, end=100):
    tc = cell._tc
    tc_pr = tc.get_or_add_tcPr()
    tc_mar = tc_pr.first_child_found_in("w:tcMar")
    if tc_mar is None:
        tc_mar = OxmlElement("w:tcMar")
        tc_pr.append(tc_mar)
    for name, value in (("top", top), ("start", start), ("bottom", bottom), ("end", end)):
        node = tc_mar.find(qn(f"w:{name}"))
        if node is None:
            node = OxmlElement(f"w:{name}")
            tc_mar.append(node)
        node.set(qn("w:w"), str(value))
        node.set(qn("w:type"), "dxa")


def set_table_borders(table, color="D9D9D9", size="6"):
    tbl_pr = table._tbl.tblPr
    borders = tbl_pr.find(qn("w:tblBorders"))
    if borders is None:
        borders = OxmlElement("w:tblBorders")
        tbl_pr.append(borders)
    for edge in ("top", "left", "bottom", "right", "insideH", "insideV"):
        element = borders.find(qn(f"w:{edge}"))
        if element is None:
            element = OxmlElement(f"w:{edge}")
            borders.append(element)
        element.set(qn("w:val"), "single")
        element.set(qn("w:sz"), size)
        element.set(qn("w:color"), color)


def repeat_table_header(row):
    tr_pr = row._tr.get_or_add_trPr()
    tbl_header = OxmlElement("w:tblHeader")
    tbl_header.set(qn("w:val"), "true")
    tr_pr.append(tbl_header)


def prevent_row_split(row):
    tr_pr = row._tr.get_or_add_trPr()
    cant_split = OxmlElement("w:cantSplit")
    tr_pr.append(cant_split)


def add_page_number(paragraph):
    paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = paragraph.add_run("Page ")
    run.font.size = Pt(8)
    begin = OxmlElement("w:fldChar")
    begin.set(qn("w:fldCharType"), "begin")
    instr = OxmlElement("w:instrText")
    instr.set(qn("xml:space"), "preserve")
    instr.text = " PAGE "
    separate = OxmlElement("w:fldChar")
    separate.set(qn("w:fldCharType"), "separate")
    text = OxmlElement("w:t")
    text.text = "1"
    end = OxmlElement("w:fldChar")
    end.set(qn("w:fldCharType"), "end")
    run._r.extend([begin, instr, separate, text, end])


def style_run(run, size=9, bold=False, color="000000"):
    run.font.name = "Arial"
    run._element.get_or_add_rPr().rFonts.set(qn("w:ascii"), "Arial")
    run._element.get_or_add_rPr().rFonts.set(qn("w:hAnsi"), "Arial")
    run.font.size = Pt(size)
    run.font.bold = bold
    run.font.color.rgb = RGBColor.from_string(color)


def add_dictionary_table(doc, number, display_name, db_name, summary, fields):
    heading = doc.add_paragraph(style="Heading 1")
    heading.paragraph_format.keep_with_next = True
    heading.paragraph_format.space_before = Pt(12)
    heading.paragraph_format.space_after = Pt(3)
    run = heading.add_run(f"Table {number}  {display_name}")
    style_run(run, size=13, bold=True)

    db_para = doc.add_paragraph()
    db_para.paragraph_format.keep_with_next = True
    db_para.paragraph_format.space_after = Pt(2)
    db_run = db_para.add_run(db_name)
    style_run(db_run, size=9, bold=True, color="4F4F4F")

    summary_para = doc.add_paragraph(summary)
    summary_para.paragraph_format.keep_with_next = True
    summary_para.paragraph_format.space_after = Pt(6)
    for run in summary_para.runs:
        style_run(run, size=9)

    table = doc.add_table(rows=1, cols=3)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.autofit = False
    set_table_borders(table)
    widths = [Inches(1.55), Inches(1.55), Inches(4.1)]
    headers = ["Field Name", "Data Type", "Description"]
    header = table.rows[0]
    repeat_table_header(header)
    for index, cell in enumerate(header.cells):
        cell.width = widths[index]
        cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
        set_cell_margins(cell, 100, 110, 100, 110)
        set_cell_shading(cell, "1F4E78")
        paragraph = cell.paragraphs[0]
        paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER
        paragraph.paragraph_format.space_after = Pt(0)
        run = paragraph.add_run(headers[index])
        style_run(run, size=9, bold=True, color="FFFFFF")

    for row_index, values in enumerate(fields, start=1):
        row = table.add_row()
        prevent_row_split(row)
        for index, value in enumerate(values):
            cell = row.cells[index]
            cell.width = widths[index]
            cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
            set_cell_margins(cell)
            if row_index % 2 == 0:
                set_cell_shading(cell, "EEF4F8")
            paragraph = cell.paragraphs[0]
            paragraph.paragraph_format.space_after = Pt(0)
            paragraph.paragraph_format.line_spacing = 1.05
            paragraph.alignment = WD_ALIGN_PARAGRAPH.LEFT
            run = paragraph.add_run(value)
            style_run(run, size=8.5, bold=(index == 0))

    spacer = doc.add_paragraph()
    spacer.paragraph_format.space_after = Pt(2)


doc = Document()
section = doc.sections[0]
doc.settings.odd_and_even_pages_header_footer = False
section.different_first_page_header_footer = False
section.page_width = Inches(8.5)
section.page_height = Inches(11)
section.top_margin = Inches(0.65)
section.bottom_margin = Inches(0.65)
section.left_margin = Inches(0.65)
section.right_margin = Inches(0.65)

doc.core_properties.title = "VehiSights Updated Database Data Dictionary"
doc.core_properties.subject = "Current Supabase database schema for capstone documentation"
doc.core_properties.author = "VehiSights Capstone Team"

styles = doc.styles
styles["Normal"].font.name = "Arial"
styles["Normal"]._element.rPr.rFonts.set(qn("w:ascii"), "Arial")
styles["Normal"]._element.rPr.rFonts.set(qn("w:hAnsi"), "Arial")
styles["Normal"].font.size = Pt(10)

title = doc.add_paragraph(style="Title")
title.alignment = WD_ALIGN_PARAGRAPH.CENTER
title.paragraph_format.space_before = Pt(12)
title.paragraph_format.space_after = Pt(4)
title_run = title.add_run("VehiSights Updated Database Data Dictionary")
style_run(title_run, size=20, bold=True)

subtitle = doc.add_paragraph()
subtitle.alignment = WD_ALIGN_PARAGRAPH.CENTER
subtitle.paragraph_format.space_after = Pt(14)
subtitle_run = subtitle.add_run("Current Supabase Schema for Capstone Documentation")
style_run(subtitle_run, size=11, color="555555")

intro = doc.add_paragraph(
    "This document defines the current VehiSights database structure used by the Laravel web application, "
    "the manual traffic-counting workflow, scheduled camera counting, reporting, prediction, Google account "
    "verification, and authenticated API access. The definitions reflect the live Supabase PostgreSQL schema "
    "reviewed on November 5, 2026."
)
intro.paragraph_format.space_after = Pt(8)
intro.paragraph_format.line_spacing = 1.15
for run in intro.runs:
    style_run(run, size=10)

scope = doc.add_paragraph()
scope.paragraph_format.space_after = Pt(12)
lead = scope.add_run("Scope note. ")
style_run(lead, size=9.5, bold=True)
body = scope.add_run(
    "Nullable fields may contain no value. JSON fields store structured vehicle-count or configuration data. "
    "The live PostgreSQL schema uses timestamp values without an attached time-zone designation."
)
style_run(body, size=9.5)

for header in (section.header, section.even_page_header, section.first_page_header):
    header_para = header.paragraphs[0]
    header_para.alignment = WD_ALIGN_PARAGRAPH.LEFT
    header_run = header_para.add_run("VehiSights Database Data Dictionary")
    style_run(header_run, size=8, color="666666")

for footer in (section.footer, section.even_page_footer, section.first_page_footer):
    add_page_number(footer.paragraphs[0])

for index, table_data in enumerate(tables, start=1):
    add_dictionary_table(doc, index, *table_data)

doc.add_page_break()
support_heading = doc.add_paragraph(style="Heading 1")
support_heading.paragraph_format.space_after = Pt(5)
support_run = support_heading.add_run("Laravel Support Tables")
style_run(support_run, size=15, bold=True)

support_intro = doc.add_paragraph(
    "The following tables support Laravel framework operations. They may be placed in an appendix when the "
    "capstone manuscript focuses only on the application's business data."
)
support_intro.paragraph_format.space_after = Pt(10)
for run in support_intro.runs:
    style_run(run, size=10)

start_number = len(tables) + 1
for offset, table_data in enumerate(support_tables):
    add_dictionary_table(doc, start_number + offset, *table_data)

OUTPUT.parent.mkdir(parents=True, exist_ok=True)
doc.save(OUTPUT)
print(str(OUTPUT))
