# Chapter 4: System Design Diagrams Reference

Here are the diagrams structured exactly as you would draw them in software like EdrawMax, Visio, or draw.io. You can use these as a direct reference for your final document.

## 1. Detailed Class Diagram (4.3.5)
This diagram shows the main entities (classes), their attributes, their functions (methods), and how they relate to each other.

```mermaid
classDiagram
    class User {
        +int user_id
        +String username
        +String password
        +String email
        +String role
        +authenticateUser()
        +hashPassword()
    }
    class Student {
        +int student_id
        +int parent_id
        +int section_id
        +String first_name
        +String last_name
        +String gender
    }
    class Section {
        +int section_id
        +int grade_id
        +int homeroom_teacher_id
        +String section_name
        +int capacity
    }
    class Subject {
        +int subject_id
        +String subject_name
        +String subject_code
    }
    class Mark {
        +int mark_id
        +int student_id
        +int subject_id
        +float score
        +calculateAverage()
    }
    class WeeklyReport {
        +int report_id
        +int student_id
        +int teacher_id
        +JSON metrics
        +String character_theme
        +generateReport()
    }
    
    User "1" -- "*" Student : is parent of
    User "1" -- "*" Section : is homeroom teacher
    Section "1" -- "*" Student : contains
    Student "1" -- "*" Mark : receives
    Subject "1" -- "*" Mark : has
    Student "1" -- "*" WeeklyReport : receives
    User "1" -- "*" WeeklyReport : creates
```

---

## 2. Persistent Data Modeling / ER Diagram (4.3.3)
This diagram illustrates how the database tables are connected to one another through foreign keys.

```mermaid
erDiagram
    USERS ||--o{ STUDENTS : "parent_id"
    USERS ||--o{ SECTIONS : "homeroom_teacher"
    USERS ||--o{ TEACHER_SUBJECTS : "assigned to"
    GRADES ||--o{ SECTIONS : "has"
    SECTIONS ||--o{ STUDENTS : "enrolls"
    SUBJECTS ||--o{ TEACHER_SUBJECTS : "taught as"
    SECTIONS ||--o{ TEACHER_SUBJECTS : "taught in"
    STUDENTS ||--o{ MARKS : "achieves"
    SUBJECTS ||--o{ MARKS : "recorded for"
    STUDENTS ||--o{ ATTENDANCE : "has"
    STUDENTS ||--o{ WEEKLY_REPORTS : "receives"
    USERS ||--o{ COMMENTS : "sends/receives"
```

---

## 3. Package Diagram (4.3.6)
This diagram shows how the actual source code folders (packages) are grouped and how they depend on each other.

```mermaid
classDiagram
    namespace WPTCS_Architecture {
        class Config {
            <<Database & Env>>
        }
        class Includes {
            <<Core Helpers>>
            +auth.php
            +functions.php
            +session.php
        }
        class Lang {
            <<Localization>>
            +en.php
            +am.php
        }
        class Modules {
            <<Application Logic>>
        }
        class AdminModule {
            <<Admin Views>>
        }
        class TeacherModule {
            <<Teacher Views>>
        }
        class ParentModule {
            <<Parent Views>>
        }
        class PrincipalModule {
            <<Principal Views>>
        }
        class Assets {
            <<CSS/JS/Images>>
        }
    }
    Modules *-- AdminModule
    Modules *-- TeacherModule
    Modules *-- ParentModule
    Modules *-- PrincipalModule
    Modules ..> Includes : uses
    Modules ..> Config : uses
    Modules ..> Lang : uses
```

---

## 4. Hardware / Software Mapping / Deployment Diagram (4.3.2)
This shows the physical and network layout of the system, mapping software components to physical hardware nodes.

```mermaid
flowchart TD
    subgraph Client_Nodes [Client Hardware Nodes]
        Browser[Personal Computer\nWeb Browser: Chrome/Edge]
        Mobile[Smartphone/Tablet\nMobile Web Browser]
    end

    subgraph Internet [Network]
        HTTP[HTTP/HTTPS Connection]
    end

    subgraph Server_Node [Server Hardware Node: Cloud / Local Host]
        subgraph Web_Server_Layer [Web Server Software]
            Apache[Apache HTTP Server]
            PHP[PHP 8.2 Runtime Environment]
        end
        subgraph Database_Layer [Database Software]
            MySQL[(MySQL 8.0 DBMS)]
        end
    end

    Browser <-->|Web Requests| HTTP
    Mobile <-->|Web Requests| HTTP
    HTTP <-->|Routes Traffic| Apache
    Apache <-->|Executes Logic| PHP
    PHP <-->|PDO SQL Queries| MySQL
```
