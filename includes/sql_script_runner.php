<?php
declare(strict_types=1);

/** Execute a checked-in SQL script one statement at a time on the same connection. */
function executeSqlScript(PDO $conn, string $sql): void {
    $statement = '';
    $hasCode = false;
    $quote = '';
    $lineComment = false;
    $blockComment = false;
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';
        if ($lineComment) {
            $statement .= $char;
            if ($char === "\n") $lineComment = false;
            continue;
        }
        if ($blockComment) {
            $statement .= $char;
            if ($char === '*' && $next === '/') {
                $statement .= '/';
                $i++;
                $blockComment = false;
            }
            continue;
        }
        if ($quote !== '') {
            $statement .= $char;
            if ($char === '\\' && $next !== '') {
                $statement .= $next;
                $i++;
            } elseif ($char === $quote) {
                if ($next === $quote) {
                    $statement .= $next;
                    $i++;
                } else {
                    $quote = '';
                }
            }
            continue;
        }
        if (($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) || $char === '#') {
            $lineComment = true;
            $statement .= $char;
            if ($char === '-') { $statement .= $next; $i++; }
            continue;
        }
        if ($char === '/' && $next === '*') {
            $blockComment = true;
            $statement .= '/*';
            $i++;
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $hasCode = true;
            $statement .= $char;
            continue;
        }
        if ($char === ';') {
            if ($hasCode) {
                $result = $conn->query(trim($statement));
                if ($result) $result->closeCursor();
            }
            $statement = '';
            $hasCode = false;
            continue;
        }
        if (!ctype_space($char)) $hasCode = true;
        $statement .= $char;
    }
    if ($quote !== '' || $blockComment) throw new RuntimeException('Unterminated SQL script.');
    if ($hasCode) {
        $result = $conn->query(trim($statement));
        if ($result) $result->closeCursor();
    }
}
