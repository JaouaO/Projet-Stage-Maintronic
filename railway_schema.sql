-- =====================================================================
-- Demo database for Railway deployment (MySQL/MariaDB)
-- Safe, anonymized schema close to the original to keep app code intact.
-- Charset/collation kept to latin1_swedish_ci where applicable.
-- =====================================================================

-- Adjust db name if needed:
CREATE DATABASE IF NOT EXISTS app_centrale_demo
  CHARACTER SET latin1
  COLLATE latin1_swedish_ci;
USE app_centrale_demo;

SET NAMES latin1;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Table: agence
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS agence;
CREATE TABLE agence (
  CodeAg VARCHAR(4) NOT NULL,
  LibAg  VARCHAR(50) DEFAULT NULL,
  PRIMARY KEY (CodeAg)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ---------------------------------------------------------------------
-- Table: t_salarie (minimal, columns kept to what your code may use)
-- Kept: 1..21, 46. Removed: 22..45 per your request.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS t_salarie;
CREATE TABLE t_salarie (
  CodeSal        VARCHAR(5)  NOT NULL,
  NomSal         VARCHAR(35) DEFAULT NULL,
  password       VARCHAR(250) DEFAULT NULL,
  resetPassword  TINYINT(1)  NOT NULL DEFAULT 0,
  PassSal        VARCHAR(20)  DEFAULT NULL,
  CodeAgSal      VARCHAR(4)   DEFAULT NULL,

  automenu1      VARCHAR(20)  NOT NULL DEFAULT '0000000000',
  automenu2      VARCHAR(20)  NOT NULL DEFAULT '0000000000',
  automenu3      VARCHAR(30)  DEFAULT  '0000000000',
  automenu4      VARCHAR(20)  NOT NULL DEFAULT '0000000000',
  automenu5      VARCHAR(20)  NOT NULL DEFAULT '0000000000',
  automenu6      VARCHAR(20)  NOT NULL DEFAULT '0000000000',
  automenu7      VARCHAR(20)  NOT NULL DEFAULT '0000000000',
  automenu8      VARCHAR(20)  NOT NULL DEFAULT '0000000000',
  automenu9      VARCHAR(20)  NOT NULL DEFAULT '0000000000',
  automenu10     VARCHAR(20)  NOT NULL DEFAULT '0000000000',
  automenu11     VARCHAR(20)  NOT NULL DEFAULT '0000000000',
  automenu12     VARCHAR(20)  NOT NULL DEFAULT 'None',

  fonction       VARCHAR(40)  NOT NULL DEFAULT '',
  LibFonction    VARCHAR(150) NOT NULL DEFAULT 'None',
  Tech_Site      INT(1)       NOT NULL DEFAULT 0,

  Obsolete       CHAR(1)      NOT NULL DEFAULT 'N',

  PRIMARY KEY (CodeSal),
  KEY idx_t_salarie_agence (CodeAgSal),
  CONSTRAINT fk_t_salarie_agence FOREIGN KEY (CodeAgSal) REFERENCES agence (CodeAg)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ---------------------------------------------------------------------
-- Table: t_resp (responsables / rattachements agence -> salarié)
-- Minimal structure used by access checks.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS t_resp;
CREATE TABLE t_resp (
  Id        INT AUTO_INCREMENT PRIMARY KEY,
  CodeAg    VARCHAR(4) NOT NULL,
  CodeSal   VARCHAR(5) NOT NULL,
  Defaut    CHAR(1)    NOT NULL DEFAULT 'N',   -- 'O' ou 'N'
  Role      VARCHAR(20) DEFAULT 'RESP',        -- libre
  UNIQUE KEY uq_resp_ag_sal (CodeAg, CodeSal),
  KEY idx_resp_ag (CodeAg),
  CONSTRAINT fk_resp_ag FOREIGN KEY (CodeAg) REFERENCES agence (CodeAg)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_resp_sal FOREIGN KEY (CodeSal) REFERENCES t_salarie (CodeSal)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ---------------------------------------------------------------------
-- Table: t_intervention (columns referenced by your services)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS t_intervention;
CREATE TABLE t_intervention (
  NumInt       VARCHAR(32) NOT NULL,
  Marque       VARCHAR(80) DEFAULT NULL,
  VilleLivCli  VARCHAR(80) DEFAULT NULL,
  CPLivCli     VARCHAR(10) DEFAULT NULL,
  CreatedAt    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  UpdatedAt    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (NumInt)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ---------------------------------------------------------------------
-- Table: t_actions_etat (état lié au dossier)
-- Used by createMinimal/updateAndPlanRdv (urgent, rdv_prev_at, commentaire, reaffecte_code)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS t_actions_etat;
CREATE TABLE t_actions_etat (
  Id             INT AUTO_INCREMENT PRIMARY KEY,
  NumIntRef      VARCHAR(32) NOT NULL,
  urgent         TINYINT(1)  NOT NULL DEFAULT 0,
  rdv_prev_at    DATETIME     DEFAULT NULL,
  commentaire    VARCHAR(500) DEFAULT NULL,
  reaffecte_code VARCHAR(5)   DEFAULT NULL,
  CreatedAt      DATETIME     DEFAULT CURRENT_TIMESTAMP,
  KEY idx_etat_numint (NumIntRef),
  CONSTRAINT fk_etat_interv FOREIGN KEY (NumIntRef) REFERENCES t_intervention (NumInt)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ---------------------------------------------------------------------
-- Table: t_histoint (historique des interventions)
-- minimal, extensible
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS t_histoint;
CREATE TABLE t_histoint (
  Id          INT AUTO_INCREMENT PRIMARY KEY,
  NumIntRef   VARCHAR(32) NOT NULL,
  Stamp       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Auteur      VARCHAR(5)   DEFAULT NULL,     -- CodeSal auteur
  Action      VARCHAR(50)  DEFAULT NULL,
  Details     TEXT,
  KEY idx_hist_numint (NumIntRef),
  CONSTRAINT fk_hist_interv FOREIGN KEY (NumIntRef) REFERENCES t_intervention (NumInt)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ---------------------------------------------------------------------
-- Table: t_planning_technicien (RDV temp/validés)
-- Used by rdvTemp*, agenda, etc.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS t_planning_technicien;
CREATE TABLE t_planning_technicien (
  Id             INT AUTO_INCREMENT PRIMARY KEY,
  NumIntRef      VARCHAR(32) NOT NULL,
  CodeTech       VARCHAR(5)  NOT NULL,      -- correspond à t_salarie.CodeSal
  StartDate      DATE        NOT NULL,
  StartTime      TIME        NOT NULL,
  StartDateTime  DATETIME    AS (TIMESTAMP(StartDate, StartTime)) STORED,
  IsValidated    TINYINT(1)  DEFAULT 0,     -- 0=temp, 1=validé
  Contact        VARCHAR(120) DEFAULT NULL,
  Marque         VARCHAR(80)  DEFAULT NULL,
  CP             VARCHAR(10)  DEFAULT NULL,
  Ville          VARCHAR(80)  DEFAULT NULL,
  Commentaire    VARCHAR(500) DEFAULT NULL,
  IsUrgent       TINYINT(1)   DEFAULT 0,
  CreatedAt      DATETIME      DEFAULT CURRENT_TIMESTAMP,
  KEY idx_plan_numint (NumIntRef),
  KEY idx_plan_code (CodeTech),
  KEY idx_plan_date (StartDate),
  CONSTRAINT fk_plan_interv FOREIGN KEY (NumIntRef) REFERENCES t_intervention (NumInt)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_plan_tech FOREIGN KEY (CodeTech) REFERENCES t_salarie (CodeSal)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ---------------------------------------------------------------------
-- Table: t_horaire (plages horaires autorisées)
-- Minimal version: par salarié, jours 1(lun)..7(dim), créneaux
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS t_horaire;
CREATE TABLE t_horaire (
  Id        INT AUTO_INCREMENT PRIMARY KEY,
  CodeSal   VARCHAR(5) NOT NULL,
  Jour      TINYINT    NOT NULL,   -- 1 = Lundi ... 7 = Dimanche
  HDeb      TIME       NOT NULL,
  HFin      TIME       NOT NULL,
  KEY idx_horaire_sal (CodeSal),
  CONSTRAINT fk_hor_sal FOREIGN KEY (CodeSal) REFERENCES t_salarie (CodeSal)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ---------------------------------------------------------------------
-- Table: t_horaireexcept (exceptions calendrier)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS t_horaireexcept;
CREATE TABLE t_horaireexcept (
  Id        INT AUTO_INCREMENT PRIMARY KEY,
  CodeSal   VARCHAR(5) NOT NULL,
  JourDate  DATE       NOT NULL,
  Autorise  TINYINT(1) NOT NULL DEFAULT 0, -- 0=non autorisé, 1=autorisé
  Motif     VARCHAR(120) DEFAULT NULL,
  KEY idx_hexp_sal (CodeSal),
  CONSTRAINT fk_hexp_sal FOREIGN KEY (CodeSal) REFERENCES t_salarie (CodeSal)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ---------------------------------------------------------------------
-- Table: t_log_util (journal d'accès)
-- Must include `id` column as per your insert
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS t_log_util;
CREATE TABLE t_log_util (
  id         VARCHAR(64)  NOT NULL,
  IP         VARCHAR(45)  NOT NULL,
  Util       VARCHAR(5)   NOT NULL,     -- CodeSal
  Agence     VARCHAR(4)   NOT NULL,
  DateAcces  DATE         NOT NULL,
  HeureAcces VARCHAR(5)   NOT NULL,     -- 'HH:MM'
  Demat      VARCHAR(10)  DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_log_util (Util),
  CONSTRAINT fk_log_ag FOREIGN KEY (Agence) REFERENCES agence (CodeAg)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ---------------------------------------------------------------------
-- Table: t_log_util_histo (optionnel simple)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS t_log_util_histo;
CREATE TABLE t_log_util_histo (
  idh        INT AUTO_INCREMENT PRIMARY KEY,
  id         VARCHAR(64)  NOT NULL,
  IP         VARCHAR(45)  NOT NULL,
  Util       VARCHAR(5)   NOT NULL,
  Agence     VARCHAR(4)   NOT NULL,
  Stamp      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ---------------------------------------------------------------------
-- Table: t_actions_vocabulaire (vocabulaire libre/labels)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS t_actions_vocabulaire;
CREATE TABLE t_actions_vocabulaire (
  Id     INT AUTO_INCREMENT PRIMARY KEY,
  Code   VARCHAR(32) NOT NULL,
  Label  VARCHAR(100) NOT NULL,
  Categ  VARCHAR(50)  DEFAULT NULL,
  UNIQUE KEY uq_vocab (Code, Categ)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ---------------------------------------------------------------------
-- Table: module_index (non critique, conservé minimalement)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS module_index;
CREATE TABLE module_index (
  Id     INT AUTO_INCREMENT PRIMARY KEY,
  Code   VARCHAR(32) NOT NULL,
  Label  VARCHAR(100) NOT NULL,
  Actif  TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_module (Code)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

SET FOREIGN_KEY_CHECKS = 1;

USE app_centrale_demo;

SET NAMES latin1;

-- agences
INSERT INTO agence (CodeAg, LibAg) VALUES
  ('M06A','Montreuil A'),
  ('M31T','Toulouse T'),
  ('M64P','Pau P'),
  ('DOAG','Direction Ouest (DEMO)'),
  ('PLUS','Direction Générale (DEMO)');

-- salariés (anonymisés)
INSERT INTO t_salarie (CodeSal, NomSal, password, resetPassword, PassSal, CodeAgSal, fonction, LibFonction, Tech_Site, Obsolete)
VALUES
  ('DEDA','Denis Deda', NULL, 0, NULL, 'M06A', 'TECH', 'Technicien', 1, 'N'),
  ('STMI','Sophie Tmi', NULL, 0, NULL, 'M31T', 'TECH', 'Technicien', 1, 'N'),
  ('PAUL','Paul Demo',  NULL, 0, NULL, 'M64P', 'TECH', 'Technicien', 1, 'N'),
  ('DIR01','Direction', NULL, 0, NULL, 'PLUS', 'DIR',  'Direction',   0, 'N');

-- responsables
INSERT INTO t_resp (CodeAg, CodeSal, Defaut, Role) VALUES
  ('M06A','DEDA','O','RESP'),
  ('M31T','STMI','O','RESP'),
  ('M64P','PAUL','O','RESP'),
  ('DOAG','DIR01','O','DIR');

-- interventions seed (mettez 2510 = Oct 2025 pour coller à votre usage)
INSERT INTO t_intervention (NumInt, Marque, VilleLivCli, CPLivCli)
VALUES
  ('M06A-2510-00001','BrandX','Montreuil','93100'),
  ('M31T-2510-00001','BrandY','Toulouse','31000'),
  ('M64P-2510-00001','BrandZ','Pau','64000');

-- etats
INSERT INTO t_actions_etat (NumIntRef, urgent, rdv_prev_at, commentaire, reaffecte_code)
VALUES
  ('M06A-2510-00001', 1, '2025-11-02 10:30:00', 'Interv urgente', NULL),
  ('M31T-2510-00001', 0, '2025-11-03 14:00:00', 'Interv standard', 'STMI'),
  ('M64P-2510-00001', 0, NULL, 'Sans RDV prévu', NULL);

-- planning: 2 temporaires + 1 validé
INSERT INTO t_planning_technicien (NumIntRef, CodeTech, StartDate, StartTime, IsValidated, Contact, Marque, CP, Ville, Commentaire, IsUrgent)
VALUES
  ('M06A-2510-00001','DEDA','2025-11-02','10:30:00',0,'Mme X','BrandX','93100','Montreuil','Créneau proposé',1),
  ('M31T-2510-00001','STMI','2025-11-03','14:00:00',1,'M. Y','BrandY','31000','Toulouse','Créneau validé',0),
  ('M64P-2510-00001','PAUL','2025-11-05','09:00:00',0,'Mme Z','BrandZ','64000','Pau','Créneau proposé',0);

-- horaires autorisés: lun(1) à ven(5) 08h-19h pour chaque salarie
INSERT INTO t_horaire (CodeSal, Jour, HDeb, HFin)
SELECT CodeSal, d.jour, '08:00:00','19:00:00'
FROM t_salarie s
JOIN (SELECT 1 jour UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5) d;

-- exceptions (vide par défaut)
-- INSERT INTO t_horaireexcept (...) VALUES (...);

-- modules (exemple)
INSERT INTO module_index (Code, Label, Actif) VALUES
  ('INTERVENTIONS','Gestion interventions',1),
  ('PLANNING','Planning techniciens',1);

-- vocabulaire (exemple)
INSERT INTO t_actions_vocabulaire (Code, Label, Categ) VALUES
  ('OBJ_STD','Standard','OBJET'),
  ('OBJ_URG','Urgent','OBJET');
