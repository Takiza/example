<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'url',
        'user_id',
        'server_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function scopeSearch($query, $search)
    {
        if (!is_null($search)) {
            return $query->where('email', 'like', '%' . $search . '%')
                ->orWhere('id', 'like', '%' . $search . '%');
        }

        return $query;
    }

    public function scopeUsers($query, $itemIds)
    {
        if (!is_null($itemIds)) {
            $itemIds = explode(',', $itemIds);

            return $query->whereIn('user_id', $itemIds);
        }

        return $query;
    }

    public function scopeServers($query, $itemIds)
    {
        if (!is_null($itemIds)) {
            $itemIds = explode(',', $itemIds);

            return $query->whereIn('server_id', $itemIds);
        }

        return $query;
    }

    public function scopeDomains($query, $itemIds)
    {
        if (!is_null($itemIds)) {
            $itemIds = explode(',', $itemIds);

            return $query->whereIn('id', $itemIds);
        }

        return $query;
    }

    public function scopeIndex($query, $request)
    {
        return $query
            ->servers($request->server_ids)
            ->users($request->user_ids)
            ->domains($request->domain_ids);
    }
}
