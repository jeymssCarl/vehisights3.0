<style id="reportDocumentStyles">
  @page { size: A4 landscape; margin: 11mm 10mm 12mm; }
  .report-document {
    --report-maroon: #7b1d1d;
    --report-ink: #171717;
    --report-muted: #66615b;
    --report-line: #d8d3ce;
    --report-soft: #f7f4f2;
    background: #fff;
    color: var(--report-ink);
    font-family: "DejaVu Sans", Arial, sans-serif;
    font-size: 9px;
    line-height: 1.35;
  }
  .report-masthead { margin-bottom: 13px; text-align: center; }
  .report-republic { margin: 0 0 2px; font-size: 10px; font-weight: 700; }
  .report-agency { margin: 0; font-size: 8px; font-weight: 600; }
  .report-title { margin: 8px 0 3px; color: var(--report-maroon); font-size: 17px; letter-spacing: .6px; }
  .report-subtitle { margin: 0; color: var(--report-muted); font-size: 7px; letter-spacing: .7px; text-transform: uppercase; }
  .report-rule { width: 48px; height: 2px; margin: 8px auto 0; background: var(--report-maroon); }
  .report-meta,
  .report-summary,
  .report-data,
  .report-footer { width: 100%; border-collapse: collapse; }
  .report-meta { margin-bottom: 10px; border: 1px solid var(--report-line); }
  .report-meta td { width: 25%; padding: 6px 8px; border-right: 1px solid var(--report-line); vertical-align: top; }
  .report-meta td:last-child { border-right: 0; }
  .report-field-label,
  .report-summary-label { display: block; margin-bottom: 2px; color: var(--report-muted); font-size: 6.5px; font-weight: 700; letter-spacing: .55px; text-transform: uppercase; }
  .report-field-value { font-size: 8px; font-weight: 600; }
  .report-summary { margin-bottom: 10px; table-layout: fixed; }
  .report-summary td { padding: 6px 8px; border-left: 3px solid var(--report-maroon); background: var(--report-soft); }
  .report-summary-spacer { width: 5px; padding: 0 !important; border: 0 !important; background: transparent !important; }
  .report-summary-value { color: var(--report-maroon); font-size: 12px; font-weight: 700; }
  .report-data { table-layout: fixed; font-size: 6.5px; }
  .report-data thead { display: table-header-group; }
  .report-data tr { page-break-inside: avoid; }
  .report-data th {
    padding: 5px 2px;
    border: 1px solid #b9b3ad;
    background: #eee9e5;
    color: #332f2c;
    font-size: 6px;
    font-weight: 700;
    line-height: 1.15;
    text-align: center;
    text-transform: uppercase;
  }
  .report-data td { padding: 4px 2px; border: 1px solid var(--report-line); text-align: center; vertical-align: middle; }
  .report-data tbody tr:nth-child(even) { background: #faf9f8; }
  .report-data .report-station { padding-left: 4px; text-align: left; }
  .report-source { color: var(--report-muted); font-size: 6px; font-weight: 700; text-transform: uppercase; }
  .report-empty { padding: 24px !important; color: var(--report-muted); text-align: center !important; }
  .report-footer { margin-top: 10px; color: var(--report-muted); font-size: 6.5px; }
  .report-footer td:last-child { text-align: right; }
  @media screen {
    .report-document {
      min-width: 1420px;
      padding: 32px;
      border: 1px solid #e4dfdb;
      border-radius: 12px;
      box-shadow: 0 14px 35px rgba(24, 20, 18, .08);
      font-size: 13px;
      line-height: 1.45;
    }
    .report-masthead { margin-bottom: 22px; }
    .report-republic { margin-bottom: 4px; font-size: 15px; }
    .report-agency { font-size: 12px; }
    .report-title { margin: 12px 0 5px; font-size: 24px; }
    .report-subtitle { font-size: 10px; }
    .report-rule { width: 64px; height: 3px; margin-top: 12px; }
    .report-meta { margin-bottom: 14px; }
    .report-meta td { padding: 11px 13px; }
    .report-field-label,
    .report-summary-label { margin-bottom: 4px; font-size: 9px; }
    .report-field-value { font-size: 12px; }
    .report-summary { margin-bottom: 16px; }
    .report-summary td { padding: 11px 13px; }
    .report-summary-value { font-size: 17px; }
    .report-data { font-size: 11px; }
    .report-data th { padding: 10px 5px; font-size: 9px; line-height: 1.3; }
    .report-data td { padding: 9px 5px; }
    .report-data .report-station { padding-left: 9px; }
    .report-source { font-size: 9px; }
    .report-empty { padding: 36px !important; }
    .report-footer { margin-top: 14px; font-size: 10px; }
  }
  @media print {
    body { margin: 0; background: #fff; }
    .report-document { min-width: 0; }
  }
</style>
