-- ============================================================================
-- Beachapedia - Script de création des tables
-- Généré à partir de l'export de données Beachapedia-11-07-26.sql
-- (cet export ne contenait que des INSERT, sans CREATE TABLE :
--  la structure ci-dessous a été reconstituée à partir des colonnes/valeurs)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ----------------------------------------------------------------------------
-- Table : texts
-- Table de traductions, une ligne par clé de texte (TID) et une colonne
-- par langue.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `texts`;
CREATE TABLE `texts` (
  `TID` VARCHAR(150) NOT NULL,
  `EN` TEXT NULL,
  `DE` TEXT NULL,
  `ES` TEXT NULL,
  `FR` TEXT NULL,
  `IT` TEXT NULL,
  `JP` TEXT NULL,
  `PT` TEXT NULL,
  `ZH-HANS` TEXT NULL,
  `NL` TEXT NULL,
  `NO` TEXT NULL,
  `TR` TEXT NULL,
  `KR` TEXT NULL,
  `RU` TEXT NULL,
  `ZH-HANT` TEXT NULL,
  `AR` TEXT NULL,
  `ID` TEXT NULL,
  `MS` TEXT NULL,
  `VI` TEXT NULL,
  `TH` TEXT NULL,
  `FI` TEXT NULL,
  PRIMARY KEY (`TID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : buildingid
-- Catalogue des types de bâtiments.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `buildingid`;
CREATE TABLE `buildingid` (
  `ID` INT NOT NULL,
  `TID` VARCHAR(60) NOT NULL,
  `Class` VARCHAR(30) NOT NULL,
  `Ordre` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_buildingid_tid` (`TID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : characterid
-- Catalogue des personnages (troupes, protos, officiers, héros).
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `characterid`;
CREATE TABLE `characterid` (
  `ID` INT NOT NULL,
  `TID` VARCHAR(70) NOT NULL,
  `Class` VARCHAR(30) NOT NULL,
  `HQUnlock` INT NOT NULL DEFAULT 0,
  `IconExportName` VARCHAR(80) NULL,
  `Officer` VARCHAR(70) NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_characterid_tid` (`TID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : abilitieid
-- Catalogue des capacités (héros / officiers).
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `abilitieid`;
CREATE TABLE `abilitieid` (
  `id` INT NOT NULL,
  `TID` VARCHAR(90) NOT NULL,
  `Type` VARCHAR(40) NOT NULL,
  `IconExportName` VARCHAR(90) NULL,
  `hero` VARCHAR(70) NULL,
  `unlock_order` VARCHAR(70) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_abilitieid_tid` (`TID`),
  KEY `idx_abilitieid_hero` (`hero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : engravingid
-- Catalogue des gravures (engravings).
-- NB : dans l'export, la colonne ID est fournie entre quotes -> stockée
-- en VARCHAR pour rester fidèle aux données existantes.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `engravingid`;
CREATE TABLE `engravingid` (
  `ID` VARCHAR(10) NOT NULL,
  `TID` VARCHAR(70) NOT NULL,
  `Category` VARCHAR(30) NOT NULL,
  `Type` INT NOT NULL DEFAULT 0,
  `IconSWF` VARCHAR(60) NULL,
  `IconExportName` VARCHAR(70) NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_engravingid_tid` (`TID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : tribsid
-- Catalogue des secteurs / tribus (tribs).
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tribsid`;
CREATE TABLE `tribsid` (
  `id` INT NOT NULL,
  `TID` VARCHAR(60) NOT NULL,
  `IconExportName` VARCHAR(60) NULL,
  `RadarLvlReq` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tribsid_tid` (`TID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : buildings
-- Coûts/temps de construction par niveau de bâtiment.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `buildings`;
CREATE TABLE `buildings` (
  `TID` VARCHAR(60) NOT NULL,
  `Niveau` INT NOT NULL,
  `BuildTimeD` INT NOT NULL DEFAULT 0,
  `BuildTimeH` INT NOT NULL DEFAULT 0,
  `BuildTimeM` INT NOT NULL DEFAULT 0,
  `BuildTimeS` INT NOT NULL DEFAULT 0,
  `BuildCostGold` INT NOT NULL DEFAULT 0,
  `BuildCostWood` INT NOT NULL DEFAULT 0,
  `BuildCostStone` INT NOT NULL DEFAULT 0,
  `BuildCostIron` INT NOT NULL DEFAULT 0,
  `TownHallLevel` INT NOT NULL DEFAULT 0,
  `XpGain` INT NOT NULL DEFAULT 0,
  `ExportName` VARCHAR(60) NULL,
  PRIMARY KEY (`TID`, `Niveau`),
  CONSTRAINT `fk_buildings_tid` FOREIGN KEY (`TID`) REFERENCES `buildingid` (`TID`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : characters
-- Coûts/temps d'amélioration par niveau de personnage.
-- ATTENTION : l'export contient des doublons (TID, Niveau) connus, par ex.
-- TID_RIFLEMAN niveau 16 avec deux UpgradeHouseLevel différents. On utilise
-- donc une clé de substitution `id` plutôt qu'une contrainte UNIQUE sur
-- (TID, Niveau), pour ne pas bloquer l'import tant que ces doublons ne sont
-- pas nettoyés côté données.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `characters`;
CREATE TABLE `characters` (
  `TID` VARCHAR(70) NOT NULL,
  `Niveau` INT NOT NULL,
  `UpgradeHouseLevel` INT NOT NULL DEFAULT 0,
  `UpgradeTimeH` INT NOT NULL DEFAULT 0,
  `UpgradeCost` INT NOT NULL DEFAULT 0,
  `XpGain` INT NOT NULL DEFAULT 0,
  KEY `idx_characters_tid_niveau` (`TID`, `Niveau`),
  CONSTRAINT `fk_characters_tid` FOREIGN KEY (`TID`) REFERENCES `characterid` (`TID`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : officer_abilities
-- Coûts/temps d'amélioration par niveau de capacité d'officier/héros.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `officer_abilities`;
CREATE TABLE `officer_abilities` (
  `TID` VARCHAR(90) NOT NULL,
  `Niveau` INT NOT NULL,
  `HeroLevel` INT NOT NULL DEFAULT 0,
  `UpgradeTimeH` INT NOT NULL DEFAULT 0,
  `UpgradeTimeM` INT NOT NULL DEFAULT 0,
  `UpgradeCost` INT NOT NULL DEFAULT 0,
  `UpgradeResource` VARCHAR(40) NULL,
  PRIMARY KEY (`TID`, `Niveau`),
  CONSTRAINT `fk_officer_abilities_tid` FOREIGN KEY (`TID`) REFERENCES `abilitieid` (`TID`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : officer_talents
-- Talents disponibles par officier.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `officer_talents`;
CREATE TABLE `officer_talents` (
  `TID` VARCHAR(70) NOT NULL,
  `ActiveAbility` VARCHAR(90) NULL,
  `PassiveAbility` VARCHAR(90) NULL,
  `TalentTID1` VARCHAR(90) NULL,
  `TalentTID2` VARCHAR(90) NULL,
  `TalentTID3` VARCHAR(90) NULL,
  `TalentTID4` VARCHAR(90) NULL,
  `TalentTID5` VARCHAR(90) NULL,
  PRIMARY KEY (`TID`),
  CONSTRAINT `fk_officer_talents_tid` FOREIGN KEY (`TID`) REFERENCES `characterid` (`TID`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : engravings
-- Coûts/valeurs par palier de qualité de gravure.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `engravings`;
CREATE TABLE `engravings` (
  `TID` VARCHAR(70) NOT NULL,
  `Quality` INT NOT NULL,
  `ResearchNeeded` INT NOT NULL DEFAULT 0,
  `TokensNeeded` INT NOT NULL DEFAULT 0,
  `Values` INT NOT NULL DEFAULT 0,
  `MaxQuality` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`TID`, `Quality`),
  CONSTRAINT `fk_engravings_tid` FOREIGN KEY (`TID`) REFERENCES `engravingid` (`TID`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : cc_bonuses
-- Bonus de monuments / commandement (CC).
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `cc_bonuses`;
CREATE TABLE `cc_bonuses` (
  `id_bonus` INT NOT NULL,
  `TID` VARCHAR(60) NOT NULL,
  `MaxCount` INT NOT NULL DEFAULT 0,
  `BoostAmount` INT NOT NULL DEFAULT 0,
  `MinBuildingLevel` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_bonus`),
  UNIQUE KEY `uq_cc_bonuses_tid` (`TID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : tribs
-- Coûts/temps d'amélioration par niveau de secteur (tribu).
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tribs`;
CREATE TABLE `tribs` (
  `TID` VARCHAR(60) NOT NULL,
  `Niveau` INT NOT NULL,
  `UpgradeCost` INT NOT NULL DEFAULT 0,
  `UpgradeTimeM` INT NOT NULL DEFAULT 0,
  `RawCristalSpace` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`TID`, `Niveau`),
  CONSTRAINT `fk_tribs_tid` FOREIGN KEY (`TID`) REFERENCES `tribsid` (`TID`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : experience_levels
-- Barème d'XP par niveau de joueur.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `experience_levels`;
CREATE TABLE `experience_levels` (
  `Level` INT NOT NULL,
  `XP_per_level` INT NOT NULL DEFAULT 0,
  `xp_total` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`Level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : townhall_levels
-- Débloque/plafonds de bâtiments par niveau de QG (Palace).
-- NB : les colonnes TID_BUILDING_* sont exportées entre quotes dans le
-- dump d'origine (valeurs "0"/"1"...) -> conservées en VARCHAR pour rester
-- fidèle aux données existantes.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `townhall_levels`;
CREATE TABLE `townhall_levels` (
  `TownHallLevel` INT NOT NULL,
  `XP` INT NOT NULL DEFAULT 0,
  `TID_BUILDING_GUNSHIP` VARCHAR(5) NULL,
  `TID_BUILDING_WOOD_STORAGE` VARCHAR(5) NULL,
  `TID_BUILDING_STONE_STORAGE` VARCHAR(5) NULL,
  `TID_BUILDING_METAL_STORAGE` VARCHAR(5) NULL,
  `TID_BUILDING_HOUSING` VARCHAR(5) NULL,
  `TID_BUILDING_WOODCUTTER` VARCHAR(5) NULL,
  `TID_BUILDING_STONE_QUARRY` VARCHAR(5) NULL,
  `TID_BUILDING_METAL_MINE` VARCHAR(5) NULL,
  `TID_BUILDING_BIG_BERTHA` VARCHAR(5) NULL,
  `TID_GUARD_TOWER` VARCHAR(5) NULL,
  `TID_BUILDING_MORTAR` VARCHAR(5) NULL,
  `TID_MACHINE_GUN_NEST` VARCHAR(5) NULL,
  `TID_BUILDING_CANNON` VARCHAR(5) NULL,
  `TID_FLAME_THROWER` VARCHAR(5) NULL,
  `TID_MISSILE_LAUNCHER` VARCHAR(5) NULL,
  `TID_TRAP_TANK_MINE` VARCHAR(5) NULL,
  `TID_TRAP_MINE` VARCHAR(5) NULL,
  `TID_TRAP_SHOCK_MINE` VARCHAR(5) NULL,
  `TID_BUILDING_ARTIFACT_WORKSHOP` VARCHAR(5) NULL,
  `TID_BUILDING_ARTIFACT_STORAGE` VARCHAR(5) NULL,
  `TID_BUILDING_ARTIFACT_RESEARCH` VARCHAR(5) NULL,
  `TID_BUILDING_MAP_ROOM` VARCHAR(5) NULL,
  `TID_BUILDING_VAULT` VARCHAR(5) NULL,
  `TID_BUILDING_GOLD_STORAGE` VARCHAR(5) NULL,
  `TID_BUILDING_LABORATORY` VARCHAR(5) NULL,
  `TID_BUILDING_LANDING_SHIP` VARCHAR(5) NULL,
  `TID_BUILDING_DEEPSEA` VARCHAR(5) NULL,
  `TID_SHOCK_LAUNCHER` VARCHAR(5) NULL,
  `TID_BUILDING_PROTO_WEAPONS` VARCHAR(5) NULL,
  `RequiredBuilding` VARCHAR(60) NULL,
  `RequiredBuildingLevel` INT NOT NULL DEFAULT 0,
  `RequiredTroopLevel` INT NOT NULL DEFAULT 0,
  `TID_BUILDING_CRITTER_LAUNCHER` VARCHAR(5) NULL,
  `TID_BUILDING_CC` VARCHAR(5) NULL,
  `TID_BUILDING_BUNKER` VARCHAR(5) NULL,
  `TID_PROTOTROOP_FACTORY` VARCHAR(5) NULL,
  `TID_BUILDING_GARRISON` VARCHAR(5) NULL,
  `MaterialSlots` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`TownHallLevel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : joueurs
-- Comptes joueurs de l'application.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `joueurs`;
CREATE TABLE `joueurs` (
  `id_player` VARCHAR(20) NOT NULL,
  `pseudo` VARCHAR(60) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `qg` INT NOT NULL DEFAULT 1,
  `experience` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_player`),
  UNIQUE KEY `uq_joueurs_pseudo` (`pseudo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : progress_building
-- Progression des bâtiments par joueur (avec instances pour les bâtiments
-- multiples, ex. mines/scieries/carrières).
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `progress_building`;
CREATE TABLE `progress_building` (
  `id_player` VARCHAR(20) NOT NULL,
  `id_building` INT NOT NULL,
  `id_instance` INT NOT NULL DEFAULT 1,
  `niveau` INT NOT NULL DEFAULT 0,
  `Debloque` TINYINT(1) NULL DEFAULT 0,
  PRIMARY KEY (`id_player`, `id_building`, `id_instance`),
  CONSTRAINT `fk_progress_building_player` FOREIGN KEY (`id_player`) REFERENCES `joueurs` (`id_player`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_progress_building_building` FOREIGN KEY (`id_building`) REFERENCES `buildingid` (`ID`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : progress_character
-- Progression des personnages (troupes/protos/officiers) par joueur.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `progress_character`;
CREATE TABLE `progress_character` (
  `id_player` VARCHAR(20) NOT NULL,
  `id_character` INT NOT NULL,
  `niveau` INT NOT NULL DEFAULT 0,
  `Debloque` TINYINT(1) NULL DEFAULT 0,
  PRIMARY KEY (`id_player`, `id_character`),
  CONSTRAINT `fk_progress_character_player` FOREIGN KEY (`id_player`) REFERENCES `joueurs` (`id_player`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_progress_character_character` FOREIGN KEY (`id_character`) REFERENCES `characterid` (`ID`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : progress_ability
-- Progression des capacités d'officier/héros par joueur et par personnage.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `progress_ability`;
CREATE TABLE `progress_ability` (
  `id_player` VARCHAR(20) NOT NULL,
  `id_character` INT NOT NULL,
  `id_ability` INT NOT NULL,
  `Niveau` INT NOT NULL DEFAULT 0,
  `Debloque` TINYINT(1) NULL DEFAULT 0,
  PRIMARY KEY (`id_player`, `id_character`, `id_ability`),
  CONSTRAINT `fk_progress_ability_player` FOREIGN KEY (`id_player`) REFERENCES `joueurs` (`id_player`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_progress_ability_character` FOREIGN KEY (`id_character`) REFERENCES `characterid` (`ID`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_progress_ability_ability` FOREIGN KEY (`id_ability`) REFERENCES `abilitieid` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : progress_engraving
-- Progression des gravures par joueur.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `progress_engraving`;
CREATE TABLE `progress_engraving` (
  `id_player` VARCHAR(20) NOT NULL,
  `id_engraving` VARCHAR(10) NOT NULL,
  `niveau` INT NOT NULL DEFAULT 0,
  `Debloque` TINYINT(1) NULL DEFAULT NULL,
  PRIMARY KEY (`id_player`, `id_engraving`),
  CONSTRAINT `fk_progress_engraving_player` FOREIGN KEY (`id_player`) REFERENCES `joueurs` (`id_player`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_progress_engraving_engraving` FOREIGN KEY (`id_engraving`) REFERENCES `engravingid` (`ID`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : progress_monument
-- Progression des bonus de monument par joueur.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `progress_monument`;
CREATE TABLE `progress_monument` (
  `id_player` VARCHAR(20) NOT NULL,
  `id_bonus` INT NOT NULL,
  `nb_bonus` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_player`, `id_bonus`),
  CONSTRAINT `fk_progress_monument_player` FOREIGN KEY (`id_player`) REFERENCES `joueurs` (`id_player`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_progress_monument_bonus` FOREIGN KEY (`id_bonus`) REFERENCES `cc_bonuses` (`id_bonus`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table : progress_tribs
-- Progression des secteurs (tribus) par joueur.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `progress_tribs`;
CREATE TABLE `progress_tribs` (
  `id_player` VARCHAR(20) NOT NULL,
  `id_trib` INT NOT NULL,
  `Niveau` INT NOT NULL DEFAULT 0,
  `Debloque` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_player`, `id_trib`),
  CONSTRAINT `fk_progress_tribs_player` FOREIGN KEY (`id_player`) REFERENCES `joueurs` (`id_player`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_progress_tribs_trib` FOREIGN KEY (`id_trib`) REFERENCES `tribsid` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
