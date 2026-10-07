<?php

namespace Tests\Unit;

use App\Services\Extraction\SelectColumnParser;
use PHPUnit\Framework\TestCase;

class SelectColumnParserTest extends TestCase
{
    /**
     * Analyse de colonnes avec alias "AS".
     */
    public function test_parses_columns_with_explicit_as(): void
    {
        $sql = "SELECT a.nom_agence AS agence, c.num_compte AS num_compte, c.type_compte AS type_compte FROM amp_test.compte c";
        $columns = SelectColumnParser::parseColumns($sql);

        $this->assertEquals(['agence', 'num_compte', 'type_compte'], $columns);
    }

    /**
     * Analyse d'expressions complexes avec fonctions et alias.
     */
    public function test_parses_columns_with_functions(): void
    {
        $sql = "SELECT cl.num_client AS num_client, TRIM(cl.prenom || ' ' || cl.nom) AS nom_raison_sociale, cl.statut AS statut FROM amp_test.client cl";
        $columns = SelectColumnParser::parseColumns($sql);

        $this->assertEquals(['num_client', 'nom_raison_sociale', 'statut'], $columns);
    }

    /**
     * Colonnes sans alias (nom direct table.colonne).
     */
    public function test_parses_columns_without_alias(): void
    {
        $sql = "SELECT agence_id, nom_agence FROM amp_test.agence";
        $columns = SelectColumnParser::parseColumns($sql);

        $this->assertEquals(['agence_id', 'nom_agence'], $columns);
    }

    /**
     * Prise en compte de requêtes sous forme de tableau de lignes.
     */
    public function test_parses_sql_array_of_lines(): void
    {
        $sql = [
            "SELECT a.nom_agence AS agence,",
            "       c.num_compte AS num_compte,",
            "       c.date_ouverture AS date_ouverture",
            "FROM amp_test.compte c",
            "WHERE c.statut = 'ACTIF'",
        ];

        $columns = SelectColumnParser::parseColumns($sql);

        $this->assertEquals(['agence', 'num_compte', 'date_ouverture'], $columns);
    }

    /**
     * Requête avec CTE (WITH ...).
     */
    public function test_parses_query_with_cte(): void
    {
        $sql = "WITH summary AS (SELECT agence_id FROM amp_test.agence) SELECT s.agence_id AS agence_id, a.nom_agence AS nom_agence FROM summary s";
        $columns = SelectColumnParser::parseColumns($sql);

        $this->assertEquals(['agence_id', 'nom_agence'], $columns);
    }
}
