from pathlib import Path
from docx import Document
from docx.enum.section import WD_SECTION_START
from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.shared import Inches, Pt, RGBColor
from docx.oxml import OxmlElement
from docx.oxml.ns import qn

OUT = Path('deliverables/SENIORLINK_Updated_IMRAD.docx')
OUT.parent.mkdir(exist_ok=True)

doc = Document()
sec = doc.sections[0]
sec.page_width, sec.page_height = Inches(8.5), Inches(11)
sec.top_margin, sec.bottom_margin = Inches(.7), Inches(.65)
sec.left_margin, sec.right_margin = Inches(.64), Inches(.64)

normal = doc.styles['Normal']
normal.font.name = 'Times New Roman'
normal.font.size = Pt(10)
normal.font.color.rgb = RGBColor(0, 0, 0)
normal.paragraph_format.line_spacing = 1.0
normal.paragraph_format.space_after = Pt(4)

def shade(cell, fill):
    tcPr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement('w:shd')
    shd.set(qn('w:fill'), fill)
    tcPr.append(shd)

def paragraph(text, *, indent=True):
    p = doc.add_paragraph(style='Normal')
    p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    p.paragraph_format.first_line_indent = Inches(.18) if indent else Inches(0)
    p.add_run(text)
    return p

def lead(label, text):
    p = doc.add_paragraph(style='Normal')
    p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    p.paragraph_format.space_after = Pt(5)
    r = p.add_run(label)
    r.bold = True
    p.add_run(text)
    return p

def section(title):
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(10)
    p.paragraph_format.space_after = Pt(6)
    p.paragraph_format.keep_with_next = True
    r = p.add_run(title)
    r.font.name = 'Times New Roman'
    r.font.size = Pt(10)
    r.bold = True
    return p

def subhead(title):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(6)
    p.paragraph_format.space_after = Pt(3)
    p.paragraph_format.keep_with_next = True
    r = p.add_run(title)
    r.italic = True
    r.font.size = Pt(10)
    return p

def caption(title):
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(5)
    p.paragraph_format.space_after = Pt(3)
    p.paragraph_format.keep_with_next = True
    r = p.add_run(title)
    r.font.size = Pt(9)
    r.bold = True

def set_cell(cell, text, bold=False):
    cell.text = ''
    cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
    p = cell.paragraphs[0]
    p.paragraph_format.space_after = Pt(0)
    p.paragraph_format.space_before = Pt(0)
    r = p.add_run(text)
    r.bold = bold
    r.font.name = 'Times New Roman'
    r.font.size = Pt(8.5)

title = doc.add_paragraph()
title.alignment = WD_ALIGN_PARAGRAPH.CENTER
title.paragraph_format.space_after = Pt(8)
r = title.add_run('SENIORLINK A Rule Based Digital Profiling and Workflow Authentication System for Senior Citizen Services in Pasig City')
r.bold = True
r.font.name = 'Times New Roman'
r.font.size = Pt(17)

authors = doc.add_table(rows=1, cols=3)
authors.alignment = WD_TABLE_ALIGNMENT.CENTER
authors.autofit = False
for cell, name in zip(authors.rows[0].cells, ['Charles Adrian B. Mejia', 'Dhone Bert T. Napay', 'Jayvelyn M. Tolento']):
    set_cell(cell, name, True)
    cell.paragraphs[0].alignment = WD_ALIGN_PARAGRAPH.CENTER
    p = cell.add_paragraph('College of Computer Studies\nPamantasan ng Lungsod ng Pasig\nPasig City, Philippines')
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_after = Pt(0)
    for run in p.runs:
        run.font.name = 'Times New Roman'
        run.font.size = Pt(8)

doc.add_paragraph().paragraph_format.space_after = Pt(1)

body_sec = doc.add_section(WD_SECTION_START.CONTINUOUS)
body_sec.top_margin, body_sec.bottom_margin = Inches(.7), Inches(.65)
body_sec.left_margin, body_sec.right_margin = Inches(.64), Inches(.64)
cols = body_sec._sectPr.xpath('./w:cols')[0]
cols.set(qn('w:num'), '2')
cols.set(qn('w:space'), '360')
cols.set(qn('w:equalWidth'), '1')

lead('Abstract—', 'SENIORLINK is a web based system developed to support senior citizen records and service applications in Pasig City. It links online submissions, centralized profiles, uploaded requirements, historical records, classification, home visit findings, benefit information, and local digital Senior Citizen ID records. The study used a developmental research design guided by the Rapid Application Development model and a quantitative descriptive evaluation. Requirements were gathered through observation and interviews. Twenty purposively selected respondents, comprising 12 Main OSCA personnel, four senior citizens, and four IT experts, rated 15 statements across six software quality areas. The 300 ratings produced a total weighted score of 1,098 and an overall weighted mean of 3.66, interpreted as Strongly Agree. All six quality area means were within the Strongly Agree interval. The findings show that the developed modules can organize connected records and support the roles of Main OSCA and barangay personnel. The ratings provide positive user feedback and a basis for continued technical testing and refinement.')
lead('Keywords—', 'SENIORLINK, senior citizen records, application tracking, role based access, rule based validation, software quality')

section('I. INTRODUCTION')
paragraph('Senior citizen services require staff to manage personal records, check supporting documents, review applications, and coordinate with barangay personnel. When information is stored in separate files, staff may need to search more than one source to understand a person’s application history. Senior citizens who have difficulty traveling also need a way to submit documents and check requests without repeated office visits. These needs shaped the development of SENIORLINK for the Office for Senior Citizens Affairs (OSCA) in Pasig City.')
paragraph('SENIORLINK connects a public application portal with Main OSCA and Senior Help Desk Officer (SHDO) workspaces. Its centralized profile links each senior citizen to applications, uploaded requirements, historical records, classification, home visit reports, benefit information, and local digital ID records. The system uses fixed rules to flag incomplete or possibly inconsistent information. Authorized Main OSCA personnel still review documents and make final administrative decisions. SHDO personnel perform permitted monitoring and assigned home visit work within their barangay.')
paragraph('Related work supports this design. Libadia et al. [1] described centralized collection, storage, classification, and retrieval of OSCA records. Anggraini et al. [2] examined the organization and controlled retrieval of electronic documents. Plattfaut and Grisold [3] emphasized the role of process management in digital change, while Melendres and Aranda [4] developed and assessed a web based resident information system. SENIORLINK applies these ideas to the local senior citizen workflow by connecting records, applications, access rules, and field findings in one platform.')
paragraph('The study addressed five questions: how the system manages connected records and modules; how it verifies approved seniors and tracks separate requests; how role and barangay restrictions organize staff access; how rule checks and assisted processes support application review; and how users and IT experts rate the system’s selected quality areas. This paper presents the development approach, the observed system functions, the questionnaire results, and the conclusions drawn from them.')

section('II. METHODOLOGY')
subhead('A. Research Design and Requirements')
paragraph('The study used a developmental research design and a quantitative descriptive evaluation. The researchers observed work in Main OSCA and at a barangay Senior Help Desk and interviewed selected personnel about applications, record keeping, requirements, staff responsibilities, and home visits. Existing forms and office procedures were also reviewed. These activities informed the system requirements; they were not treated as a separate survey of service outcomes.')
paragraph('Development followed the four phases of Rapid Application Development (RAD): requirements planning, user design, construction, and cutover. In requirements planning, the team identified user roles, records, application steps, and limits of the system. User design translated those needs into page layouts, workflow steps, and data structures. Construction produced and refined working modules. Cutover prepared the working system for demonstration, testing, and respondent evaluation. Feedback during development was used to revise screens and functions.')
subhead('B. System Design and Functional Checks')
paragraph('SENIORLINK used a three layer design. Browser pages formed the presentation layer; a PHP application handled requests, permissions, and validation; and a MySQL database stored profiles, applications, staff assignments, statuses, and related records. The application controlled database access and linked uploaded documents to the relevant application. The system operated as a local service platform and did not connect directly to eGovPH, national agency databases, banks, or digital wallets.')
paragraph('The principal functions included online registration and benefit requests, centralized profiles, document upload, historical Excel or CSV import with preview, local digital ID records, and classification. The Classification page counted each primary senior profile once and allowed Main OSCA to record Active or Inactive status and residence within or outside Pasig, together with a reason and effective date. Deceased status came from the existing deceased registry. Classification was a record management aid; it did not change benefit eligibility or application status.')
paragraph('For identity and request tracking, each senior profile had a Permanent Tracking Number. After a Senior Citizen ID application was approved and an official ID number assigned, the two identifiers supported access to available local benefit applications. Each submitted application received its own Transaction ID, so multiple requests could remain linked to one profile without sharing a request identifier. Role checks limited access by staff responsibility and, for SHDO personnel, by assigned barangay.')
paragraph('The application used fixed if then rules to flag missing fields, invalid formats, missing requirements, age related concerns, and possible duplicate records. Staff reviewed these flags and source documents. Main OSCA scheduled Local Pension home visits when needed; assigned SHDO personnel recorded findings and returned them for Main OSCA review. The team checked major processes by entering data or selecting an action and comparing the saved record, displayed result, warning, or status with the expected outcome. The paper reports these checks as functional observations and does not claim measured processing time or a formal accuracy rate.')
subhead('C. Respondents, Instrument, and Analysis')
paragraph('Purposive sampling selected 20 respondents: 12 Main OSCA personnel, four senior citizens, and four IT experts. Main OSCA respondents represented office use, senior citizens represented the public portal, and IT experts provided a technical perspective. SHDO personnel and authorized representatives were intended users but were not part of the questionnaire sample.')
paragraph('Each respondent answered a 15 item questionnaire using a four point scale from Strongly Disagree (1) to Strongly Agree (4). The selected ISO/IEC 25010 based areas were Functionality, Reliability, Usability, Efficiency, Portability, and Maintainability [5]. Frequency counts summarized individual responses. A weighted mean was computed by multiplying each rating value by its frequency, adding the weighted scores, and dividing by the number of valid ratings. Means from 3.26 to 4.00 were interpreted as Strongly Agree. Results were rounded to two decimal places; no significance tests were reported.')
paragraph('All 20 respondents rated all 15 items, giving 300 valid ratings. The researchers distinguished questionnaire feedback from functional checks: a high mean shows respondent agreement with an evaluation statement, while a feature description or screen shows what the system was designed to do. Neither one alone establishes faster service or error free operation. The study also addressed institutional permission, informed consent, voluntary participation, confidentiality, and restricted access to research and system records.')

section('III. RESULTS AND DISCUSSION')
subhead('A. Centralized Records and Classification')
paragraph('For the first research question, the developed system connected public applications, electronic requirements, senior profiles, historical imports, home visit findings, local benefit schedules, and approved local digital ID records. Authorized Main OSCA staff could open information linked to a senior profile and review the related application history. The historical import process checked file structure and values and showed a preview so staff could examine records before confirming them. The result reflects the record organization approach discussed by Libadia et al. [1] and Anggraini et al. [2].')
paragraph('Classification added a citywide view of registered seniors. A primary profile was counted once even when it had several linked applications. The page displayed activity status, residence classification, effective date, and a reason for changes. Main OSCA could update Active or Inactive classifications; Deceased status was drawn from the deceased registry. The report helped staff examine the state of the records without acting as an eligibility decision. This feature extended the first research question from record storage to record classification and reporting.')
subhead('B. Verification and Individual Requests')
paragraph('For the second question, the Permanent Tracking Number identified the senior profile, while the official Senior Citizen ID number supported identity checking after approval. A different Transaction ID identified each submitted benefit application. For example, one senior could make two benefit requests that stayed connected to the same profile but had separate IDs and statuses. This distinction let staff review one request without confusing it with another. Matching credentials supported access to an approved record; it did not establish entitlement to a benefit.')
subhead('C. Staff Access and Application Support')
paragraph('For the third question, Main OSCA personnel handled document review and final application decisions. Main OSCA administrators managed staff accounts and assignments. SHDO personnel viewed permitted records and approved digital IDs for their assigned barangay and submitted findings for assigned home visits. Role and barangay checks organized what each user could see or do. The arrangement supported a clear separation of citywide review and barangay field tasks.')
paragraph('For the fourth question, the system flagged incomplete entries, invalid formats, missing documents, and possible duplicate profiles for staff review. It also supported applications submitted with help from family members or authorized representatives. When a Local Pension application required a home visit, Main OSCA scheduled the task, SHDO recorded the findings, and Main OSCA considered the report with the other application materials. Assisted submission and home visits provided access to the normal process; they did not give an application special priority or automatic approval. Fixed rules helped staff identify issues while preserving human review of eligibility.')
subhead('D. Software Quality Evaluation')
paragraph('For the fifth question, all 300 questionnaire responses were Agree or Strongly Agree: 102 were Agree and 198 were Strongly Agree. Their total weighted score was 1,098. Dividing by 300 yielded an overall weighted mean of 3.66, interpreted as Strongly Agree. Table I shows the six area means. Functionality had the highest mean at 3.75; Reliability had the lowest at 3.58. The difference between them was 0.17, and every area mean remained within the Strongly Agree interval.')
caption('TABLE I  SOFTWARE QUALITY EVALUATION')
t = doc.add_table(rows=1, cols=3)
t.alignment = WD_TABLE_ALIGNMENT.CENTER
t.autofit = False
for c, text in zip(t.rows[0].cells, ['Quality area', 'Mean', 'Interpretation']):
    set_cell(c, text, True); shade(c, 'E8EDF3')
for vals in [('Functionality','3.75','Strongly Agree'),('Reliability','3.58','Strongly Agree'),('Usability','3.68','Strongly Agree'),('Efficiency','3.70','Strongly Agree'),('Portability','3.60','Strongly Agree'),('Maintainability','3.60','Strongly Agree'),('Overall','3.66','Strongly Agree')]:
    row=t.add_row()
    for c,text in zip(row.cells, vals): set_cell(c,text)
for row in t.rows:
    for cell in row.cells:
        tcPr=cell._tc.get_or_add_tcPr()
        borders=OxmlElement('w:tcBorders')
        for edge in ('bottom',):
            tag=OxmlElement('w:'+edge); tag.set(qn('w:val'),'single'); tag.set(qn('w:color'),'B6C3D2'); tag.set(qn('w:sz'),'3'); borders.append(tag)
        tcPr.append(borders)

paragraph('The respondent groups also gave positive ratings. Senior citizens had a mean of 3.82, Main OSCA personnel 3.67, and IT experts 3.47; each was interpreted as Strongly Agree. The overall mean used all individual ratings, rather than averaging the three group means without regard to group size. The findings are consistent with the use of structured quality assessment for an information system described by Melendres and Aranda [4], although the scores are specific to SENIORLINK and its respondents.')
paragraph('The questionnaire indicates that participating users and reviewers generally agreed with the selected quality statements. It does not provide a measured record retrieval time, processing time, backup restoration result, or large scale deployment result. Reliability, Portability, and Maintainability had comparatively lower means than Functionality and Efficiency, so further technical checks in recovery, installation, and behavior after changes would be useful. This interpretation keeps participant feedback distinct from the functional observations described in the first four questions.')

section('IV. CONCLUSION')
paragraph('SENIORLINK brought the study’s five questions into one web based system. It connected senior profiles with online applications, uploaded documents, historical records, classification, benefit information, home visit findings, and local digital ID records. A Permanent Tracking Number identified a senior profile and a separate Transaction ID identified each submitted application. Staff access followed user role and barangay assignment; rule checks identified information requiring attention, while authorized Main OSCA personnel retained final administrative authority.')
paragraph('The 20 respondents gave the system an overall weighted mean of 3.66, interpreted as Strongly Agree, and all six selected quality areas received the same interpretation. These results show positive feedback on the developed system within the evaluated sample. The study demonstrates a working way to organize related senior citizen service records and processes, with further technical measurement needed before claims about speed, accuracy, or wider deployment can be made.')

section('V. RECOMMENDATIONS')
paragraph('Main OSCA should continue reviewing staff roles, barangay assignments, and validation rules as office procedures change. The team should test backup restoration, deployment in the intended environment, and system behavior after revisions. Regular checks of historical import results and duplicate warnings can help staff maintain useful records. Home visit coordination should keep the SHDO field report separate from Main OSCA’s final decision.')
paragraph('Future evaluations should include SHDO personnel, authorized representatives, more senior citizens, and additional barangays. Researchers can measure record retrieval and application processing times, historical import accuracy, and performance with larger datasets. These measurements can complement the questionnaire ratings and guide improvements to accessibility, reliability, and maintenance.')

section('REFERENCES')
refs = [
    '[1] M. Libadia, K. A. Botangen, P. J. Lucero, and J. Tecson, “Development of a web-based records management system: An ERMS initiative for the Office of Senior Citizen Affairs in the Philippines,” Records Management Journal, vol. 35, no. 2, pp. 160–179, 2025, doi: 10.1108/RMJ-06-2023-0031.',
    '[2] D. Anggraini, K. Adi, and J. E. Suseno, “Electronic document management systems implementation across industries: Systematic analysis,” Indonesian Journal of Electrical Engineering and Computer Science, vol. 36, no. 1, pp. 264–273, 2024, doi: 10.11591/ijeecs.v36.i1.pp264-273.',
    '[3] R. Plattfaut and S. Grisold, “Business process management as a continuous enabler for digital innovation: Insights from top executives,” Business Process Management Journal, vol. 31, no. 8, pp. 244–266, 2025, doi: 10.1108/BPMJ-05-2025-0759.',
    '[4] U. M. Melendres and K. M. Aranda, “Development and evaluation of a web-based resident information management system,” Journal of Computer, Software, and Program, vol. 1, no. 1, pp. 14–22, 2024, doi: 10.69739/jcsp.v1i1.50.',
    '[5] ISO/IEC 25010:2011, Systems and software engineering—Systems and software Quality Requirements and Evaluation (SQuaRE)—System and software quality models, 2011.'
]
for ref in refs:
    p=doc.add_paragraph(ref)
    p.paragraph_format.first_line_indent=Inches(-.2)
    p.paragraph_format.left_indent=Inches(.2)
    p.paragraph_format.space_after=Pt(3)
    p.alignment=WD_ALIGN_PARAGRAPH.JUSTIFY

doc.core_properties.title = 'SENIORLINK IMRAD Research Paper'
doc.core_properties.subject = 'Updated IMRAD based on the SENIORLINK capstone paper'
doc.core_properties.author = 'Charles Adrian B. Mejia; Dhone Bert T. Napay; Jayvelyn M. Tolento'
doc.save(OUT)
print(OUT)
