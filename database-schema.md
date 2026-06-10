@startuml SecureVault

hide circle
skinparam linetype ortho
skinparam classAttributeIconSize 0

entity User {
    * id : BIGINT
    --
    email : VARCHAR(255)
    password : VARCHAR(255)
    firstName : VARCHAR(100)
    lastName : VARCHAR(100)
    isActive : BOOLEAN
    emailVerified : BOOLEAN
    createdAt : TIMESTAMP
    updatedAt : TIMESTAMP
}

entity Role {
    * id : BIGINT
    --
    name : VARCHAR(100)
    description : TEXT
}

entity Permission {
    * id : BIGINT
    --
    code : VARCHAR(100)
    description : TEXT
}

entity Vault {
    * id : BIGINT
    --
    name : VARCHAR(255)
    description : TEXT
    archived : BOOLEAN
    createdAt : TIMESTAMP
    updatedAt : TIMESTAMP
}

entity VaultPermission {
    * id : BIGINT
    --
    code : VARCHAR(50)
    name : VARCHAR(100)
    description : TEXT
}

entity SharedVault {
    * id : BIGINT
    --
    accepted : BOOLEAN
    sharedAt : TIMESTAMP
}

entity PasswordEntry {
    * id : BIGINT
    --
    title : VARCHAR(255)
    username : VARCHAR(255)
    encryptedPassword : TEXT
    url : VARCHAR(500)
    notes : TEXT
    favorite : BOOLEAN
    createdAt : TIMESTAMP
    updatedAt : TIMESTAMP
}

entity Category {
    * id : BIGINT
    --
    name : VARCHAR(100)
    color : VARCHAR(20)
}

entity Tag {
    * id : BIGINT
    --
    name : VARCHAR(100)
    color : VARCHAR(20)
}

entity SecurityAlert {
    * id : BIGINT
    --
    type : VARCHAR(50)
    severity : VARCHAR(20)
    message : TEXT
    resolved : BOOLEAN
    createdAt : TIMESTAMP
}

entity Notification {
    * id : BIGINT
    --
    title : VARCHAR(255)
    message : TEXT
    type : VARCHAR(50)
    isRead : BOOLEAN
    isSent : BOOLEAN
    sentAt : TIMESTAMP
    createdAt : TIMESTAMP
}

entity ActivityLog {
    * id : BIGINT
    --
    action : VARCHAR(255)
    ipAddress : VARCHAR(50)
    userAgent : TEXT
    createdAt : TIMESTAMP
}

entity LoginAttempt {
    * id : BIGINT
    --
    ipAddress : VARCHAR(50)
    success : BOOLEAN
    createdAt : TIMESTAMP
}

entity PasswordHistory {
    * id : BIGINT
    --
    previousPasswordHash : TEXT
    changedAt : TIMESTAMP
}

' ===================================
' MANY TO MANY
' ===================================

User }o--o{ Role : has

Role }o--o{ Permission : contains

PasswordEntry }o--o{ Category : categorized

PasswordEntry }o--o{ Tag : tagged

Vault }o--o{ Tag : tagged

' ===================================
' ONE TO MANY
' ===================================

User ||--o{ Vault : owns

User ||--o{ Notification : receives

User ||--o{ ActivityLog : generates

User ||--o{ SecurityAlert : receives

User ||--o{ LoginAttempt : performs

Vault ||--o{ PasswordEntry : contains

Vault ||--o{ SharedVault : shared

VaultPermission ||--o{ SharedVault : grants

User ||--o{ SharedVault : accesses

PasswordEntry ||--o{ PasswordHistory : history

@enduml