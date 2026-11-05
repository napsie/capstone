from docx import Document
from docx.shared import Inches, Pt
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.section import WD_SECTION_START
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.oxml import OxmlElement
from docx.oxml.ns import qn

OUT = r'D:\xampp1\htdocs\capstone\SENIORLINK_Updated_IMRAD.docx'
d=Document()
s=d.sections[0]
s.page_width=Inches(8.5); s.page_height=Inches(11)
s.top_margin=Inches(.7); s.bottom_margin=Inches(.65)
s.left_margin=Inches(.65); s.right_margin=Inches(.65)
s.header_distance=Inches(.3); s.footer_distance=Inches(.3)

normal=d.styles['Normal']; normal.font.name='Times New Roman'; normal.font.size=Pt(11)
normal.paragraph_format.space_after=Pt(6)
normal.paragraph_format.line_spacing=1.0
for style in ['Title','Heading 1','Heading 2']:
    d.styles[style].font.name='Times New Roman'
    d.styles[style].font.color.rgb=None
d.styles['Title'].font.size=Pt(16); d.styles['Title'].font.bold=True
title_ppr=d.styles['Title']._element.pPr
if title_ppr is not None:
    for border in title_ppr.findall(qn('w:pBdr')): title_ppr.remove(border)
d.styles['Heading 1'].font.size=Pt(11); d.styles['Heading 1'].font.bold=True
d.styles['Heading 1'].paragraph_format.space_before=Pt(10)
d.styles['Heading 1'].paragraph_format.space_after=Pt(4)

def para(txt, boldlead=None, align=None):
    p=d.add_paragraph(style='Normal')
    if boldlead and txt.startswith(boldlead):
        p.add_run(boldlead).bold=True; p.add_run(txt[len(boldlead):])
    else: p.add_run(txt)
    if align is not None: p.alignment=align
    return p
def head(txt): d.add_paragraph(txt,style='Heading 1')

p=d.add_paragraph(style='Title'); p.alignment=WD_ALIGN_PARAGRAPH.CENTER
p.add_run('SENIORLINK A Rule Based Digital Profiling and Workflow Authentication System for Senior Citizen Services in Pasig City')
p=d.add_paragraph(); p.alignment=WD_ALIGN_PARAGRAPH.CENTER
p.add_run('Charles Adrian B. Mejia  •  Dhone Bert T. Napay  •  Jayvelyn M. Tolento').bold=True
p=d.add_paragraph(); p.alignment=WD_ALIGN_PARAGRAPH.CENTER
p.add_run('College of Computer Studies, Pamantasan ng Lungsod ng Pasig')
para('Abstract—SENIORLINK is a web based system designed to connect senior citizen records and service applications handled by the Office for Senior Citizens Affairs (OSCA) in Pasig City. The study used a developmental research design and the Rapid Application Development model. Requirements came from observations and interviews with OSCA and Senior Help Desk Officer (SHDO) personnel. The prototype linked senior profiles, online applications, electronic requirements, historical record imports, classification, home visits, benefit information, and approved digital Senior Citizen IDs. A Permanent Tracking Number identified a senior’s profile, while a separate Transaction ID identified each application. Predefined rules flagged incomplete or inconsistent submissions for staff review. Twenty purposively selected respondents—12 Main OSCA personnel, four senior citizens, and four IT experts—evaluated 15 statements across six software quality areas. The 300 ratings produced an overall weighted mean of 3.66, interpreted as Strongly Agree. The findings show positive user assessments of the prototype. Wider user evaluation and technical testing remain necessary before broader deployment.',boldlead='Abstract—')
para('Keywords—senior citizen services, records management, application tracking, role based access, Rapid Application Development, software quality',boldlead='Keywords—')

sec=d.add_section(WD_SECTION_START.CONTINUOUS)
sec.top_margin=Inches(.7); sec.bottom_margin=Inches(.65)
sec.left_margin=Inches(.65); sec.right_margin=Inches(.65)
sec._sectPr.xpath('./w:cols')[0].set(qn('w:num'),'2')
sec._sectPr.xpath('./w:cols')[0].set(qn('w:space'),'360')

head('I. INTRODUCTION')
para('Senior citizen services involve applications, supporting documents, identity records, local benefit information, and decisions made by authorized personnel. In Pasig City, Main OSCA handles citywide review and administration, while SHDO personnel assist with barangay monitoring and assigned home visits. When these activities depend on separate files and manual records, staff may have difficulty locating an applicant’s history, checking requirements, and following a request through each stage. Seniors who have limited mobility may also need help submitting forms or completing a home visit.')
para('Earlier work on electronic records emphasizes organized storage and controlled retrieval [1]. Local government studies also point to the need to connect people, processes, and technology when services move online [2]. SENIORLINK applies these ideas to senior citizen services through a web based platform that joins the applicant’s profile with related applications and documents. The system supports staff review; it does not decide whether a person is entitled to a benefit.')
para('The study examined five questions: how the system manages connected records and classifications; how it verifies approved seniors and tracks separate applications; how roles and barangay assignments limit access; how rules and assisted processing support applications and home visits; and how users rate the system across six selected software quality areas. This article reports the development method, implemented workflow, evaluation results, and limits of the findings.')
para('The project focused on local senior citizen services. Its approved digital ID is a record produced after the local Senior Citizen ID process, not a connection to a national identification database. The same scope applies to benefit requests: SENIORLINK shows available local services and helps organize requests, while the official requirements and final decisions remain with Main OSCA. These boundaries shaped both the design and the interpretation of the study.')

head('II. METHODS')
para('A developmental research design was used to build the prototype, followed by a quantitative descriptive evaluation. The team observed work at Main OSCA and the Senior Help Desk in Barangay Pinagbuhatan and conducted semi structured interviews with selected personnel. These activities identified record keeping needs, application steps, document requirements, user roles, and issues in retrieving and monitoring records. The findings were translated into page designs, database fields, access rules, and process flows.')
para('Development followed the four stages of Rapid Application Development: requirements planning, user design, construction, and cutover. Requirements planning identified the users and their tasks. User design produced the public portal, Main OSCA workspace, and SHDO workspace. During construction, the team built linked profile, application, document, tracking, classification, reporting, and home visit functions, and revised the prototype after checking the workflows. Cutover prepared a demonstration version for evaluation. The study did not assess citywide operational deployment.')
para('The system uses a presentation layer for its pages, an application layer for workflows and validation, and a data layer for stored records. A Permanent Tracking Number belongs to the senior citizen profile. After a Senior Citizen ID application is approved and an official ID number is assigned, the two credentials support access to local benefit applications. Every submitted application receives its own Transaction ID and status history. This separates a person’s identity from individual requests.')
para('Main OSCA personnel review applications and retain authority for administrative decisions. Main OSCA administrators manage staff accounts and barangay assignments. SHDO personnel use a barangay limited workspace for permitted monitoring, approved digital ID viewing, and assigned home visit work. Signed in SHDO personnel do not administer staff accounts. Rule based checks flag missing fields, invalid formats, missing documents, age requirements, and possible duplicates. Staff examine those flags and supporting documents before deciding on an application.')
para('Functional checking compared expected and observed outputs for online submission, document upload and retrieval, historical CSV or Excel import, application tracking, role and barangay restrictions, home visit routing, and reporting. A separate questionnaire measured respondents’ assessments. Purposive sampling selected 20 participants: 12 Main OSCA personnel, four senior citizens, and four IT experts. SHDO personnel and authorized representatives were not included among survey respondents. Each participant rated 15 statements on a four point scale, giving 300 ratings in total.')
para('The functional checks followed the application workflow rather than treating each page as an isolated screen. For example, an online submission was checked for its saved profile, attached requirements, assigned identifier, and status shown to the applicant. A scheduled visit was checked through assignment, SHDO recording, and return of the findings to Main OSCA. Historical imports were previewed before confirmation so staff could inspect missing values or possible duplicate records. These checks identified whether the intended steps could be completed in the prototype; the paper did not report a measured system wide error rate or a formal performance benchmark.')
para('The questionnaire covered Functionality, Reliability, Usability, Efficiency, Portability, and Maintainability, using selected quality criteria related to ISO/IEC 25010 [3]. Frequency counts and weighted means summarized the responses. The overall weighted mean used all 300 ratings rather than an unweighted mean of the three respondent groups. Means of 3.26–4.00 were interpreted as Strongly Agree, 2.51–3.25 as Agree, 1.76–2.50 as Disagree, and 1.00–1.75 as Strongly Disagree. No significance test was used. Participation was voluntary, and the study described permission, consent, confidentiality, and restricted access to research records.')
para('The instrument assigned three statements to Functionality, two to Reliability, four to Usability, and two each to Efficiency, Portability, and Maintainability. These statements asked about perceived suitability, accuracy, security, recovery, ease of use, response behavior, installation, and the ability to examine and test the software. The responses therefore describe the participants’ judgments of the prototype. A rating on recoverability, for example, was not treated as proof that a backup had been restored successfully. The researchers kept this distinction when presenting questionnaire findings beside the functional workflow checks.')

head('III. RESULTS')
para('The prototype connected online submissions, uploaded requirements, historical record import, benefit information, home visit findings, and approved digital Senior Citizen IDs to a senior citizen profile. Main OSCA could review the connected application history. The classification page counted each primary senior profile once and displayed activity and residence categories. Authorized Main OSCA personnel could record Active or Inactive status and residence within or outside Pasig, with a reason and effective date. Deceased status came from the deceased registry. Classification supported record review and reports without changing benefit eligibility or application status.')
para('The historical import workflow accepted records through a preview and confirmation step instead of placing every uploaded row directly into the main record set. This mattered because older spreadsheets could use different column names or contain incomplete information. The connected profile then provided a common point for viewing documents, decisions, and service history. The reported result is that the prototype organized these functions in one workflow; it does not by itself establish that retrieval is faster than the previous manual process.')
para('For identity and tracking, the Permanent Tracking Number remained attached to one senior profile. A separate Transaction ID was created for each submitted benefit application. This allowed staff to identify one request and its status while keeping it connected to the applicant. Issuing an identifier did not itself establish eligibility. The Main OSCA and SHDO workspaces separated citywide administration from barangay monitoring and field tasks. The displayed records and available actions followed the user role and, for SHDO personnel, the assigned barangay.')
para('The rule based checks brought incomplete entries, missing requirements, unexpected formats, and possible duplicates to staff attention. Possible matches were flagged for review rather than automatically merged or rejected. Family members or authorized representatives could assist seniors with online forms. When a Local Pension application required a home visit, Main OSCA scheduled the visit, SHDO recorded its findings, and Main OSCA considered the report with the rest of the application. Home visits did not provide automatic approval or queue priority.')
para('Reports were also part of the prototype. The classification report summarized registered seniors by activity and current residence, while other reports presented records relevant to their source page and selected filters. A report could be exported for administrative review. The reporting function was designed to show the stored information in a readable form; the study did not compare the time needed to prepare these reports with the former process.')
para('The implemented flow also gave different users different views of the same case. A public applicant could submit details and follow the request status. Main OSCA could review documents, route a field task, and record the final action. An assigned SHDO worker could enter home visit findings without taking over the citywide decision. Linking these steps to one profile reduced the need to treat each request as an unrelated record. The prototype’s screenshots and process descriptions documented that the pages and transitions were present; they did not provide a measured count of every possible error or unauthorized access attempt.')

t=d.add_table(rows=1, cols=2); t.alignment=WD_TABLE_ALIGNMENT.CENTER; t.style='Table Grid'
t.rows[0].cells[0].text='Quality area'; t.rows[0].cells[1].text='Weighted mean'
for a,v in [('Functionality','3.75'),('Reliability','3.58'),('Usability','3.68'),('Efficiency','3.70'),('Portability','3.60'),('Maintainability','3.60')]:
    c=t.add_row().cells;c[0].text=a;c[1].text=v
for c in t.rows[0].cells:
    for r in c.paragraphs[0].runs:r.bold=True
para('Table 1. Respondent ratings by software quality area.',align=WD_ALIGN_PARAGRAPH.CENTER)
para('All six area means were in the Strongly Agree interval (Table 1). Functionality had the highest mean at 3.75. Reliability was lowest at 3.58. The group means were 3.67 for Main OSCA personnel, 3.82 for senior citizens, and 3.47 for IT experts; all were interpreted as Strongly Agree. The 300 ratings comprised 198 Strongly Agree and 102 Agree responses. Their total weighted score was 1,098, yielding an overall weighted mean of 3.66.')

head('IV. DISCUSSION')
para('The results show how a centralized profile can keep related service records available in one workflow. This is consistent with records management work that emphasizes collection, organization, and controlled retrieval [1]. The classification page adds a view of registered seniors by activity and current residence, but its categories are administrative record information, not a determination of benefit entitlement. Historical record imports also require careful checking for missing fields, duplicate entries, and incorrect column mapping before they are accepted.')
para('The difference between a Permanent Tracking Number and a Transaction ID is important to the workflow. The first identifies the senior’s continuing profile; the second identifies a particular application. In the same way, role and barangay restrictions assign staff access according to their work. These design choices support traceability and division of duties, although interface descriptions alone cannot establish that every access restriction works under all conditions. Functional testing must continue to include attempts to open other barangays’ records and perform actions outside a role’s authority.')
para('Rule based validation gives staff a consistent way to locate missing or inconsistent application information. Its fixed checks must be updated when office procedures or requirements change. Assistance with online forms and home visits addresses access barriers for seniors who have difficulty traveling, while preserving the same review process for all applicants. The system therefore supports the staff decision rather than replacing it.')
para('The questionnaire results indicate that the selected respondents viewed the prototype positively. The ratings should be interpreted as user assessments, not direct measurements of processing speed, security, recovery, or reliability. The four senior citizens and four IT experts provided useful perspectives, but the sample was small and purposive. SHDO personnel and authorized representatives were not surveyed. The study also did not test performance at citywide scale. These limits are relevant when considering use beyond the evaluated prototype.')
para('The area scores suggest useful priorities for the next development cycle. Reliability, Portability, and Maintainability were the three lowest rated areas even though each remained in the Strongly Agree interval. Backup restoration, deployment in the intended environment, and testing after changes should therefore receive direct technical checks. The 0.17 difference between the highest and lowest area means shows a narrow spread in these survey ratings, but it does not prove that all technical risks are equally small. Similarly, the senior citizens’ group mean of 3.82 describes the views of four participants and should not be generalized to all older users in Pasig City.')
para('Several practical implications follow from the results. First, staff should be able to see why an application was flagged and what supporting document still needs checking. A clear warning helps review, while a hidden automatic decision could be difficult to correct. Second, any change to an applicant’s status or to a senior’s classification needs a traceable reason and responsible user, because later staff may rely on the history. Third, the information shown in an exported report should use the same filters and record definitions as the screen that produced it. These points concern the consistency of the workflow and can be checked in the next round of testing.')
para('The findings also place limits on claims about service improvement. The paper describes connected records and reports, but it does not provide before and after measurements of retrieval time, processing time, or data completeness. It would be premature to claim a measured speed gain. A future comparison could use the same set of applications in the manual and digital processes, time a defined retrieval task, and count missing or incorrectly mapped fields after import. Such measurements would give Main OSCA stronger evidence for deciding whether the prototype is ready for routine use.')

head('V. CONCLUSION')
para('SENIORLINK brought senior citizen profiles, applications, documents, classifications, home visit findings, and related service records into one connected prototype. Its Permanent Tracking Number identified a profile, while a Transaction ID identified each application. Role and barangay restrictions separated Main OSCA and SHDO work, and predefined checks supported review without making eligibility decisions. The overall weighted mean of 3.66 showed Strongly Agree assessments among the 20 respondents. The study supports the value of the prototype for organizing the described workflows, within the limits of its sample and testing.')

head('VI. RECOMMENDATIONS')
para('Before broader use, the team recommends testing backup restoration, access restrictions, historical record import, performance, and behavior after software changes. Main OSCA should review validation rules when official requirements change and continue to check flagged applications and home visit reports before deciding on eligibility. Further evaluation should include SHDO personnel, authorized representatives, more seniors, and additional barangays. Future studies can measure retrieval time, data migration accuracy, accessibility, and performance under a larger workload.')

head('REFERENCES')
refs=[
'[1] M. Libadia, K. A. Botangen, P. J. Lucero, and J. Tecson, “Development of a web-based records management system: An ERMS initiative for the Office of Senior Citizen Affairs in the Philippines,” Records Management Journal, vol. 35, no. 2, pp. 160–179, 2025. doi: 10.1108/RMJ-06-2023-0031.',
'[2] A. David et al., “Understanding local government digital technology adoption strategies: A PRISMA review,” Sustainability, vol. 15, no. 12, art. 9645, 2023. doi: 10.3390/su15129645.',
'[3] ISO/IEC, “Systems and software engineering—Systems and software Quality Requirements and Evaluation (SQuaRE)—System and software quality models,” ISO/IEC 25010:2011, 2011.'
]
for r in refs: para(r)

d.save(OUT)
print(OUT)
