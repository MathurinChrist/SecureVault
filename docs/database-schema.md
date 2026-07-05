@startuml SecureVault

hide circle
skinparam linetype ortho
skinparam classAttributeIconSize 0
skinparam shadowing false

' ===================================
' ENTITÉS
' ===================================

entity User {
    * id : INT <<PK>>
    --
    email : VARCHAR(180) <<unique>>
    password : VARCHAR(255)
    firstName : VARCHAR(255)
    lastName : VARCHAR(255)
    roles : JSON
    isActive : BOOLEAN
    emailVerified : BOOLEAN
    profileImage : VARCHAR(255)
    encryptionKey : VARCHAR(255)
    is2faEnabled : BOOLEAN
    twoFactorSecret : VARCHAR(255)
    googleId : VARCHAR(255) <<unique>>
    createdAt : TIMESTAMP
    updatedAt : TIMESTAMP
}

entity Role {
    * id : INT <<PK>>
    --
    name : VARCHAR(100) <<unique>>
    description : TEXT
}

entity Permission {
    * id : INT <<PK>>
    --
    code : VARCHAR(100) <<unique>>
    description : TEXT
}

entity Vault {
    * id : INT <<PK>>
    --
    user_id : INT <<FK>>
    name : VARCHAR(255)
    description : TEXT
    archived : BOOLEAN
    createdAt : TIMESTAMP
    updatedAt : TIMESTAMP
}

entity VaultPermission {
    * id : INT <<PK>>
    --
    code : VARCHAR(50) <<unique>>
    name : VARCHAR(100)
    description : TEXT
}

entity SharedVault {
    * id : INT <<PK>>
    --
    vault_id : INT <<FK>>
    sender_id : INT <<FK>>
    recipient_id : INT <<FK>>
    permission_id : INT <<FK>>
    accepted : BOOLEAN
    sharedAt : TIMESTAMP
}

entity PasswordEntry {
    * id : INT <<PK>>
    --
    vault_id : INT <<FK>>
    user_id : INT <<FK>>
    title : VARCHAR(255)
    username : VARCHAR(255)
    encryptedPassword : TEXT
    keyVersion : INT
    url : VARCHAR(500)
    notes : TEXT
    favorite : BOOLEAN
    createdAt : TIMESTAMP
    updatedAt : TIMESTAMP
}

note right of PasswordEntry
  keyVersion = 0 : clé partagée (legacy)
  keyVersion = 1 : clé PBKDF2 per-user
end note

entity PasswordHistory {
    * id : INT <<PK>>
    --
    password_entry_id : INT <<FK>>
    previousPasswordHash : TEXT
    changedAt : TIMESTAMP
}

entity Category {
    * id : INT <<PK>>
    --
    name : VARCHAR(100)
    color : VARCHAR(20)
}

entity Tag {
    * id : INT <<PK>>
    --
    name : VARCHAR(100)
    color : VARCHAR(20)
}

' ===================================
' STI — BaseNotification
' ===================================

entity BaseNotification {
    * id : INT <<PK>>
    --
    user_id : INT <<FK>>
    discr : VARCHAR(20)
    title : VARCHAR(255)
    type : VARCHAR(50)
    isRead : BOOLEAN
    createdAt : TIMESTAMP
    .. Alert uniquement ..
    description : TEXT
    category : VARCHAR(50)
    .. Notification uniquement ..
    message : TEXT
    isSent : BOOLEAN
    sentAt : TIMESTAMP
}

note right of BaseNotification
  STI (Single Table Inheritance)
  discr = "alert"        → Alert
  discr = "notification" → Notification
  Table : base_notification
end note

' ===================================
' AUTRES ENTITÉS
' ===================================

entity ContactMessage {
    * id : INT <<PK>>
    --
    name : VARCHAR(100)
    email : VARCHAR(180)
    subject : VARCHAR(255)
    message : TEXT
    isRead : BOOLEAN
    createdAt : TIMESTAMP
}

entity ActivityLog {
    * id : INT <<PK>>
    --
    user_id : INT <<FK>>
    action : VARCHAR(255)
    ipAddress : VARCHAR(50)
    userAgent : TEXT
    createdAt : TIMESTAMP
}

entity LoginAttempt {
    * id : INT <<PK>>
    --
    user_id : INT <<FK>>
    ipAddress : VARCHAR(50)
    success : BOOLEAN
    createdAt : TIMESTAMP
}

entity UserSession {
    * id : INT <<PK>>
    --
    user_id : INT <<FK>>
    sessionId : VARCHAR(255)
    createdAt : TIMESTAMP
    expiresAt : TIMESTAMP
}

' ===================================
' MANY TO MANY
' ===================================

User }o--o{ Role : "has"
Role }o--o{ Permission : "contains"
PasswordEntry }o--o{ Category : "categorized"
PasswordEntry }o--o{ Tag : "tagged"
Vault }o--o{ Tag : "tagged"

' ===================================
' ONE TO MANY
' ===================================

User ||--o{ Vault : "owns"
User ||--o{ BaseNotification : "receives"
User ||--o{ ActivityLog : "generates"
User ||--o{ LoginAttempt : "performs"
User ||--o{ UserSession : "opens"

Vault ||--o{ PasswordEntry : "contains"
Vault ||--o{ SharedVault : "shared via"

VaultPermission ||--o{ SharedVault : "grants"
User ||--o{ SharedVault : "accesses"

PasswordEntry ||--o{ PasswordHistory : "history"

@enduml
