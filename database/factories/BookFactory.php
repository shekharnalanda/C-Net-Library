<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Book> */
class BookFactory extends Factory
{
    protected $model = Book::class;

    public function definition(): array
    {
        return [
            'book_category_id' => null,
            'title' => fake()->sentence(4),
            'author' => fake()->name(),
            'isbn' => fake()->unique()->isbn13(),
            'language' => 'English',
            'status' => true,
        ];
    }
}
