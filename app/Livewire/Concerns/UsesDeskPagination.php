<?php

namespace App\Livewire\Concerns;

use Livewire\WithPagination;

trait UsesDeskPagination
{
    use WithPagination;

    public function paginationView(): string
    {
        return 'pagination.livewire';
    }

    public function paginationSimpleView(): string
    {
        return 'pagination.livewire';
    }
}
