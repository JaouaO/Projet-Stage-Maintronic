CREATE TABLE IF NOT EXISTS t_planning_technicien (
                                                     id INT AUTO_INCREMENT PRIMARY KEY,
                                                     NumIntRef VARCHAR(50) NOT NULL,
    IsValidated TINYINT(1) NULL,
    CodeTech VARCHAR(20) NULL,
    Debut DATETIME NULL,
    Fin DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS t_histoint (
                                          id INT AUTO_INCREMENT PRIMARY KEY,
                                          NumInt VARCHAR(50) NOT NULL,
    Action VARCHAR(255) NULL,
    CreatedAt DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS t_intervention (
                                              NumInt VARCHAR(50) PRIMARY KEY,
    Marque VARCHAR(50) NULL,
    CPLivCli VARCHAR(10) NULL,
    VilleLivCli VARCHAR(100) NULL,
    ObjetTrait VARCHAR(100) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
