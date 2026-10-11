<?php

namespace App\Dto;

final readonly class CategoryView
{
    public function __construct(
        public string $slug,
        public string $label,
    ) {
    }
}
