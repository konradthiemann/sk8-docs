<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * DQL `JSONB_CONTAINS(e.repos, :json) = TRUE` → SQL `(e.repos @> CAST(? AS jsonb))`.
 *
 * Lets repositories filter JSON list columns (agents, repos, adrs) with PostgreSQL's
 * containment operator instead of loading everything into PHP.
 */
final class JsonbContainsFunction extends FunctionNode
{
    private Node $column;
    private Node $value;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->column = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->value = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return \sprintf(
            '(%s @> CAST(%s AS jsonb))',
            $this->column->dispatch($sqlWalker),
            $this->value->dispatch($sqlWalker),
        );
    }
}
