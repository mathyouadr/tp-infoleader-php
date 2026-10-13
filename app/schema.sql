-- ============================================================
-- InfoLeader — schéma de la base MySQL (migration TP2)
-- Exécuté par app/install.php, ou à la main :
--   mysql -u root infoleader < app/schema.sql
-- ============================================================

-- Comptes administrateurs (mot de passe : password_hash())
CREATE TABLE IF NOT EXISTS admins (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Codes « mot de passe oublié » (le code est stocké haché)
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id   INT UNSIGNED NOT NULL,
    code_hash  VARCHAR(255) NOT NULL,
    expires_at INT UNSIGNED NOT NULL,          -- timestamp Unix
    attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    used       TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (admin_id),
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limitation de débit par IP et par action (connexion, oubli, code...)
CREATE TABLE IF NOT EXISTS throttle (
    ip            VARCHAR(45) NOT NULL,
    scope         VARCHAR(20) NOT NULL,
    failures      INT UNSIGNED NOT NULL DEFAULT 0,
    next_allowed  INT UNSIGNED NOT NULL DEFAULT 0, -- timestamp Unix
    blocked_until INT UNSIGNED NOT NULL DEFAULT 0, -- timestamp Unix
    updated_at    INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (ip, scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Réglages (ex. : configuration e-mail en JSON, secret SMTP chiffré)
CREATE TABLE IF NOT EXISTS settings (
    name  VARCHAR(50) PRIMARY KEY,
    value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journaux : connexions, échecs, blocages, événements, contact
CREATE TABLE IF NOT EXISTS logs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type       ENUM('login_ok', 'login_fail', 'blocked', 'event', 'contact') NOT NULL,
    ip         VARCHAR(45) NOT NULL,
    message    TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (type),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Articles du catalogue (gérés dans Administration > Articles)
CREATE TABLE IF NOT EXISTS products (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    price       DECIMAL(10, 2) NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    image       VARCHAR(255) NULL,          -- chemin relatif, ex. assets/products/clavier.jpg
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
