<?php

namespace App\DeskLint\Rules;

use App\DeskLint\Finding;
use App\DeskLint\Rule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

abstract class BaseRule implements Rule
{
    protected function db(): ConnectionInterface
    {
        return DB::connection('fec');
    }

    /** SQL fragment for live bonuses, with the table alias applied. */
    protected function activeWhere(string $alias = 'b'): string
    {
        $where = config('fec.bonus.active_where');

        // "status = 'active'" -> "b.status = 'active'" when the fragment is a bare column.
        return preg_replace('/^\s*([a-z_]+)\s*(=|<>|!=|in|like)/i', $alias.'.$1 $2', $where) ?? $where;
    }

    protected function finding(string $key, string $subject, string $detail = '', ?string $url = null): Finding
    {
        return new Finding($this->id(), $key, $subject, $detail, $url);
    }

    protected function editorUrl(int $bid): string
    {
        return sprintf(config('fec.bonus.editor_url'), $bid);
    }

    /** Casino display name from the review article title, cheap and good enough for a report. */
    protected function casinoName(int $cid): string
    {
        $title = $this->db()->table('content')->where('id', $cid)->value('title');

        return $title ? (string) $title : 'casino #'.$cid;
    }
}
