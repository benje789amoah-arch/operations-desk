<?php

namespace App\Reports;

use DateTimeInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * SQL building blocks for "what was this instance's status at time T".
 *
 * Updates are append-only and their ids increase with their server-assigned
 * created_at, so the latest update at or before T is MAX(id) among the
 * instance's updates created before T. No update before T means the instance
 * had not been updated yet ("not updated"). Everything is plain correlated
 * subqueries, portable across SQLite, MySQL and PostgreSQL.
 */
final class StatusAt
{
    /**
     * Subquery returning the id of the latest update recorded before the
     * instance's own close of day (closes_at).
     */
    public static function closeOfDayUpdateId(): QueryBuilder
    {
        return DB::table('activity_updates as u_close')
            ->selectRaw('max(u_close.id)')
            ->whereColumn('u_close.activity_instance_id', 'activity_instances.id')
            ->whereColumn('u_close.created_at', '<', 'activity_instances.closes_at');
    }

    /**
     * Subquery returning the id of the latest update recorded before $cutoff.
     */
    public static function updateIdBefore(DateTimeInterface $cutoff): QueryBuilder
    {
        return DB::table('activity_updates as u_cut')
            ->selectRaw('max(u_cut.id)')
            ->whereColumn('u_cut.activity_instance_id', 'activity_instances.id')
            ->where('u_cut.created_at', '<', $cutoff);
    }

    /**
     * Wraps an "update id" subquery to return that update's new_status.
     */
    public static function statusOf(QueryBuilder $updateId): QueryBuilder
    {
        return DB::table('activity_updates as u_status')
            ->select('u_status.new_status')
            ->whereRaw('u_status.id = ('.$updateId->toSql().')', $updateId->getBindings());
    }
}
