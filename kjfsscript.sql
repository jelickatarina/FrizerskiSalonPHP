DROP DATABASE IF EXISTS KJFS;

CREATE DATABASE KJFS;

USE KJFS;

CREATE TABLE `korisnik` (
  `KorisnikId` varchar(256) NOT NULL,
  `Lozinka` varchar(256) NOT NULL,
  `Ime` varchar(256) NOT NULL,
  `Prezime` varchar(256) NOT NULL,
  `DatumRodjenja` date NOT NULL,
  `Telefon` varchar(20) NOT NULL,
  `Email` varchar(256) NOT NULL,
  `Nivo` int(11) NOT NULL,
  PRIMARY KEY (`KorisnikId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `korisnik` (`KorisnikId`, `Lozinka`, `Ime`, `Prezime`, `DatumRodjenja`, `Telefon`, `Email`, `Nivo`) 
VALUES ('Kaca', 'Kaca', 'Katarina', 'Jelic', '2003-06-25', '0114567890', 'jelickat@gmail.com', '9');

CREATE TABLE `usluga` (
  `UslugaId` varchar(256) NOT NULL,
  `Cena` int(11) NOT NULL,
  `Trajanje` int(11) NOT NULL,
  `Opis` varchar(256) NOT NULL,
  `Aktivna` bit(1) NOT NULL,
  PRIMARY KEY (`UslugaId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `termin` (
  `TerminId` int(11) NOT NULL AUTO_INCREMENT,
  `UslugaId` varchar(256) NOT NULL,
  `KorisnikId` varchar(256) NOT NULL,
  `Datum` date NOT NULL,
  `Vreme` int(11) NOT NULL,
  `KorisnikFrizerId` varchar(256) NOT NULL,
  `Uradjeno` bit(1) NOT NULL,
  PRIMARY KEY (`TerminId`),
  FOREIGN KEY (UslugaId) REFERENCES usluga(UslugaId),
  FOREIGN KEY (KorisnikFrizerId) REFERENCES korisnik(KorisnikId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

