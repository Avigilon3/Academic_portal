# Academic Grading System Diagrams

These diagrams describe the current PHP application, role guards, and checked-in MySQL schema. Mermaid blocks can be rendered in compatible Markdown previews.

## UML Use-Case View

```mermaid
flowchart LR
    admin([Admin / Registrar])
    professor([Professor])
    student([Student])
    mail([SMTP mail service])

    subgraph system[Academic Grading System]
        ucAccounts((Manage accounts and academic records))
        ucSetup((Configure sections and grading sheets))
        ucRequests((Review document and edit requests))
        ucReports((View reports and activity logs))
        ucAnnouncements((Publish announcements))
        ucClasses((View assigned classes and rosters))
        ucGrades((Create grade items and submit scores))
        ucReopen((Request grading sheet edit access))
        ucResults((View subjects and grades))
        ucDocuments((Request or cancel documents))
        ucProfile((Manage profile and settings))
        ucInteract((Read and interact with announcements))
        ucNotifications((Receive notifications))
    end

    admin --> ucAccounts
    admin --> ucSetup
    admin --> ucRequests
    admin --> ucReports
    admin --> ucAnnouncements
    professor --> ucClasses
    professor --> ucGrades
    professor --> ucReopen
    professor --> ucInteract
    professor --> ucNotifications
    student --> ucResults
    student --> ucDocuments
    student --> ucProfile
    student --> ucInteract
    student --> ucNotifications
    mail <--> ucAnnouncements
    mail <--> ucNotifications
```

## System Architecture View

```mermaid
flowchart LR
    subgraph clients[Portal Clients]
        student[Student using a web browser]
        professor[Professor using a web browser]
        admin[Admin or Registrar using a web browser]
    end

    subgraph application[PHP Application Server]
        web[PHP web server]
        auth[Session authentication and role guards]

        subgraph portals[Role-specific PHP portals]
            studentPortal[Student portal]
            professorPortal[Professor portal]
            adminPortal[Admin portal]
        end

        core[Shared services<br/>PDO connection, notifications, mailer]
    end

    database[(MySQL database<br/>Users, academic records, grades,<br/>requests, announcements, logs)]
    smtp[Institution SMTP service]
    mailpit[Mailpit SMTP capture and inbox UI<br/>Local development only]
    recipients[Recipient email inboxes]

    student -->|HTTP request| web
    professor -->|HTTP request| web
    admin -->|HTTP request| web
    web --> auth
    auth -->|Student role| studentPortal
    auth -->|Professor role| professorPortal
    auth -->|Admin-type role| adminPortal

    studentPortal <--> core
    professorPortal <--> core
    adminPortal <--> core
    core <-->|PDO queries| database

    core -->|Configured outbound mail| smtp
    smtp -->|Email| recipients
    core -.->|Local SMTP profile| mailpit
    mailpit -.->|Captured messages| recipients

    studentPortal -->|Subjects, grades, requests, notices| student
    professorPortal -->|Classes, grading sheets, notices| professor
    adminPortal -->|Records, request queues, reports| admin
```

The role portals access MySQL through shared PHP services; client browsers do not connect directly to the database. In local development, the mail launcher uses loopback-only Mailpit instead of delivering messages to real inboxes.

## ERD

The relationships below reflect foreign keys where defined and application joins where the schema stores IDs without declaring foreign-key constraints.

```mermaid
erDiagram
    USERS {
        int id PK
        string email
        string role
        string first_name
        string last_name
        string status
    }
    STUDENTS {
        int id PK
        int user_id FK
        string student_id
        string year_level
        string status
    }
    PROFESSORS {
        int id PK
        int user_id FK
        string professor_id
        int subject_id FK
        boolean is_active
    }
    COURSES {
        int id PK
        string code
        string title
    }
    TERMS {
        int id PK
        string term_name
        string semester
        string school_year
    }
    SECTIONS {
        int id PK
        string section_name
        int course_id FK
        int term_id FK
        string year_level
    }
    SUBJECTS {
        int id PK
        string subject_code
        int course_id FK
        int term_id FK
    }
    SECTION_STUDENTS {
        int id PK
        int section_id FK
        int student_id FK
    }
    SECTION_SUBJECTS {
        int id PK
        int section_id FK
        int subject_id FK
        int professor_id FK
        int term_id FK
    }
    GRADING_SHEETS {
        int id PK
        int section_subject_id FK
        int section_id FK
        int professor_id FK
        string status
        datetime deadline_at
    }
    DEFAULT_GRADE_COMPONENTS {
        int id PK
        string name
        decimal weight
        int sort_order
    }
    GRADE_COMPONENTS {
        int id PK
        int grading_sheet_id FK
        string name
        decimal weight
    }
    GRADE_ITEMS {
        int id PK
        int component_id FK
        string title
        int total_points
    }
    GRADES {
        int id PK
        int grade_item_id FK
        int student_id FK
        int score
    }
    DOCUMENT_REQUESTS {
        int id PK
        int student_id FK
        string type
        string status
    }
    EDIT_REQUESTS {
        int id PK
        int grading_sheet_id FK
        int professor_id FK
        int decided_by FK
        string status
    }
    ANNOUNCEMENTS {
        int id PK
        int created_by FK
        string title
        datetime created_at
    }
    NOTIFICATIONS {
        int id PK
        int user_id FK
        int announcement_id FK
        string type
        boolean is_read
    }
    ANNOUNCEMENT_REACTIONS {
        int id PK
        int announcement_id FK
        int user_id FK
        string reaction_type
    }
    ANNOUNCEMENT_COMMENTS {
        int id PK
        int announcement_id FK
        int user_id FK
        int parent_comment_id FK
        string comment_text
    }
    ACTIVITY_LOGS {
        int id PK
        int user_id FK
        string action
        datetime created_at
    }

    USERS ||--o| STUDENTS : has_profile
    USERS ||--o| PROFESSORS : has_profile
    COURSES o|--o{ SECTIONS : groups
    COURSES o|--o{ SUBJECTS : offers
    TERMS o|--o{ SECTIONS : schedules
    TERMS o|--o{ SUBJECTS : offers
    SECTIONS ||--o{ SECTION_STUDENTS : enrolls
    STUDENTS ||--o{ SECTION_STUDENTS : joins
    SECTIONS ||--o{ SECTION_SUBJECTS : offers
    SUBJECTS ||--o{ SECTION_SUBJECTS : assigned_as
    PROFESSORS o|--o{ SECTION_SUBJECTS : teaches
    TERMS o|--o{ SECTION_SUBJECTS : applies_to
    SECTION_SUBJECTS ||--o{ GRADING_SHEETS : generates
    SECTIONS ||--o{ GRADING_SHEETS : contains
    PROFESSORS ||--o{ GRADING_SHEETS : owns
    GRADING_SHEETS ||--o{ GRADE_COMPONENTS : contains
    GRADE_COMPONENTS ||--o{ GRADE_ITEMS : contains
    GRADE_ITEMS ||--o{ GRADES : receives
    STUDENTS ||--o{ GRADES : earns
    STUDENTS ||--o{ DOCUMENT_REQUESTS : submits
    GRADING_SHEETS ||--o{ EDIT_REQUESTS : receives
    PROFESSORS ||--o{ EDIT_REQUESTS : submits
    USERS o|--o{ EDIT_REQUESTS : decides
    USERS o|--o{ ANNOUNCEMENTS : publishes
    USERS ||--o{ NOTIFICATIONS : receives
    ANNOUNCEMENTS o|--o{ NOTIFICATIONS : announces
    ANNOUNCEMENTS ||--o{ ANNOUNCEMENT_REACTIONS : has
    USERS ||--o{ ANNOUNCEMENT_REACTIONS : reacts
    ANNOUNCEMENTS ||--o{ ANNOUNCEMENT_COMMENTS : has
    USERS ||--o{ ANNOUNCEMENT_COMMENTS : writes
    ANNOUNCEMENT_COMMENTS o|--o{ ANNOUNCEMENT_COMMENTS : parent_of
    USERS o|--o{ ACTIVITY_LOGS : generates
```

`DEFAULT_GRADE_COMPONENTS` is a reusable template copied into sheet-specific `GRADE_COMPONENTS`; it is not directly linked to a grading sheet. Some ID relationships shown above are enforced by application queries rather than database constraints.

## DFD Level 0

```mermaid
flowchart LR
    admin[Admin / Registrar]
    professor[Professor]
    student[Student]
    system((Academic Grading System))
    mail[SMTP service<br/>Mailpit in local development]

    admin -->|Account, catalog, grading setup, approvals, announcements| system
    system -->|Dashboard, reports, audit history, request queues| admin
    professor -->|Scores, submissions, edit requests, announcement reactions/comments| system
    system -->|Classes, rosters, grading sheets, decisions, notifications| professor
    student -->|Credentials, profile changes, document requests, comments/reactions| system
    system -->|Subjects, grade reports, request status, announcements, notifications| student
    system -->|Outbound email| mail
    mail -->|Delivery acceptance or failure| system
```

## DFD Level 1

```mermaid
flowchart LR
    admin[Admin / Registrar]
    professor[Professor]
    student[Student]
    mail[SMTP service]

    auth((1.0 Authenticate and authorize))
    records((2.0 Manage academic records))
    grades((3.0 Manage grading))
    requests((4.0 Process requests))
    announcements((5.0 Publish and interact with announcements))
    reports((6.0 Produce reports and audit views))

    users[(D1 Users and profiles)]
    academic[(D2 Courses, terms, sections, subjects, rosters)]
    grading[(D3 Sheets, components, items, scores)]
    requestData[(D4 Document and edit requests)]
    communication[(D5 Announcements and notifications)]
    audit[(D6 Activity logs)]

    admin -->|Credentials| auth
    professor -->|Credentials| auth
    student -->|Credentials| auth
    auth <--> users
    auth -->|Authorized role session| records
    auth -->|Authorized role session| grades
    auth -->|Authorized role session| requests
    auth -->|Authorized role session| announcements

    admin -->|Student, professor, catalog, section changes| records
    records <--> academic
    records <--> users
    records -->|Administrative changes| audit

    admin -->|Template, assignment, deadline, status changes| grades
    professor -->|Grade items, scores, submission| grades
    student -->|Grade report query| grades
    grades <--> academic
    grades <--> grading
    grades -->|Grade-sheet decisions and changes| audit
    grades -->|Subjects and grade results| student

    student -->|Document request or cancellation| requests
    professor -->|Edit-access request| requests
    admin -->|Schedule, release, approve, deny| requests
    requests <--> requestData
    requests -->|Request status notifications| communication
    requests -->|Request decisions| professor
    requests -->|Pickup status| student
    requests -->|Administrative actions| audit

    admin -->|Announcement content| announcements
    professor -->|Reaction or comment| announcements
    student -->|Reaction or comment| announcements
    announcements <--> communication
    announcements -->|Email notification| mail
    announcements -->|In-app announcement and reactions| professor
    announcements -->|In-app announcement and reactions| student

    admin -->|Report or activity-log query| reports
    reports --> academic
    reports --> grading
    reports --> requestData
    reports --> audit
    reports -->|Summaries| admin
```

## Admin Flowchart

```mermaid
flowchart TD
    start([Start]) --> login[Enter email and password]
    login --> credentials{Credentials valid and account active?}
    credentials -- No --> loginError[Show login error]
    loginError --> login
    credentials -- Yes --> role{Admin-type role?}
    role -- No --> otherPortal[Redirect to the matching role portal]
    role -- Yes --> dashboard[Open admin dashboard]

    dashboard --> action{Choose an admin task}
    action -->|Accounts and records| records[Manage students, professors, courses, subjects, sections, terms, and masterlists]
    action -->|Grading| gradeSetup[Configure templates, sheets, deadlines, and statuses]
    action -->|Requests| requestType{Request type?}
    action -->|Announcement| publish[Write and publish announcement]
    action -->|Reports| reports[View reports or activity logs]

    requestType -->|Document| documentStatus[Schedule, mark ready, release, or cancel]
    documentStatus --> studentNotice[Notify student of request status]
    requestType -->|Grading edit| decision{Approve edit request?}
    decision -- Yes --> reopen[Reopen grading sheet]
    decision -- No --> deny[Deny edit request]
    reopen --> professorNotice[Notify professor of decision]
    deny --> professorNotice

    publish --> saveAnnouncement[Save post and create recipient notifications]
    saveAnnouncement --> smtp{SMTP configured?}
    smtp -- Yes --> sendEmail[Attempt email delivery]
    smtp -- No --> skipEmail[Keep in-app notifications]
    sendEmail --> dashboard
    skipEmail --> dashboard
    records --> dashboard
    gradeSetup --> dashboard
    studentNotice --> dashboard
    professorNotice --> dashboard
    reports --> dashboard
```

## Professor Flowchart

```mermaid
flowchart TD
    start([Start]) --> login[Enter email and password]
    login --> active{Valid credentials and active account?}
    active -- No --> loginError[Show login error]
    loginError --> login
    active -- Yes --> role{Professor role?}
    role -- No --> denied[Reject or redirect to matching portal]
    role -- Yes --> dashboard[Open professor dashboard]

    dashboard --> task{Choose task}
    task -->|Classes| sections[View assigned sections and rosters]
    task -->|Grades| chooseSheet[Choose assigned grading sheet]
    task -->|Edit access| editRequest[Select sheet and submit reason]
    task -->|Announcements| interact[Read, react, or comment]

    chooseSheet --> owned{Sheet assigned to this professor?}
    owned -- No --> unavailable[Show sheet unavailable]
    owned -- Yes --> deadline{Deadline passed?}
    deadline -- Yes --> autoLock[Lock sheet unless already reopened]
    deadline -- No --> editCheck{Draft/reopened, or submitted before deadline with edit mode?}
    autoLock --> editCheck
    editCheck -- No --> requestReopen[Request edit access from admin]
    requestReopen --> waitDecision[Wait for admin decision]
    waitDecision --> approved{Approved?}
    approved -- No --> readOnly[Keep sheet read-only]
    approved -- Yes --> reopened[Admin reopens sheet]
    reopened --> enterGrades
    editCheck -- Yes --> enterGrades[Edit grade items and student scores]
    enterGrades --> saveOrSubmit{Save draft or submit?}
    saveOrSubmit -- Save --> draft[Save as draft]
    saveOrSubmit -- Submit --> submitted[Submit sheet; reopened sheets lock on submission]

    sections --> dashboard
    editRequest --> dashboard
    interact --> dashboard
    unavailable --> dashboard
    readOnly --> dashboard
    draft --> dashboard
    submitted --> dashboard
```

## Student Flowchart

```mermaid
flowchart TD
    start([Start]) --> login[Enter email and password]
    login --> active{Valid credentials and active account?}
    active -- No --> loginError[Show login error]
    loginError --> login
    active -- Yes --> role{Student role?}
    role -- No --> denied[Reject or redirect to matching portal]
    role -- Yes --> dashboard[Open student dashboard]

    dashboard --> task{Choose task}
    task -->|Subjects| subjects[View enrolled subjects]
    task -->|Grades| grades[View report of grades]
    task -->|Documents| requestType{Request report or certificate?}
    task -->|Profile| profile[View profile or update settings]
    task -->|Announcements| interact[Read, react, or comment]

    requestType --> submitRequest[Submit document request]
    submitRequest --> pending[Track pending request]
    pending --> cancel{Cancel while pending?}
    cancel -- Yes --> cancelled[Cancel request]
    cancel -- No --> adminAction[Admin schedules, marks ready, or releases request]
    adminAction --> statusNotice[Receive status notification]

    subjects --> dashboard
    grades --> dashboard
    profile --> dashboard
    interact --> dashboard
    cancelled --> dashboard
    statusNotice --> dashboard
```

## Notes

- Login validates the password and blocks inactive accounts before redirecting by role.
- Grading-sheet statuses in the schema are `draft`, `submitted`, `locked`, and `reopened`; deadlines can lock sheets, and an approved professor edit request reopens one.
- Document-request statuses are `pending`, `scheduled`, `ready`, `released`, and `cancelled`. Professor edit-request statuses are `pending`, `approved`, and `denied`.
- The role guard accepts `mis` and `super_admin`, while the checked-in `users.role` enum lists `admin`, `registrar`, `professor`, and `student`. Confirm this role list against the deployed database before treating those additional roles as provisioned.