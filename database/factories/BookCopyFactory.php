<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<BookCopy> */
class BookCopyFactory extends Factory
{
    protected $model = BookCopy::class;

    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'branch_id' => Branch::factory(),
            'accession_no' => 'ACC-'.Str::upper(Str::random(12)),
            'barcode' => null,
            'rack_no' => fake()->optional()->bothify('R-##'),
            'condition' => 'good',
            'status' => 'available',
        ];
    }
}
