<?php
/*
 * Searching the catalogue.
 * Uses the v_book_availability view, so every book comes back with
 * total_copies and available_copies already counted.
 */
class Book
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Search by title, author or ISBN, optionally inside one category.
     * An empty search returns every book (up to 100).
     */
    public function search(string $query = '', string $category = ''): array
    {
        $sql = 'SELECT * FROM v_book_availability WHERE 1 = 1';
        $params = [];

        if ($query !== '') {
            // Escape % and _ so they are searched for literally.
            $like = '%' . addcslashes($query, '%_\\') . '%';
            $sql .= ' AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        if ($category !== '') {
            $sql .= ' AND category = ?';
            $params[] = $category;
        }

        $sql .= ' ORDER BY title LIMIT 100';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** One book with its copy counts. 404 if it does not exist. */
    public function getById(int $bookId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM v_book_availability WHERE book_id = ?');
        $stmt->execute([$bookId]);
        $book = $stmt->fetch();

        if (!$book) {
            throw new AppException('Book not found.', 404);
        }
        return $book;
    }

    /** Every category name, A–Z, e.g. ["Biology", "Computer Science", ...]. */
    public function getCategories(): array
    {
        $stmt = $this->db->query('SELECT DISTINCT category FROM books ORDER BY category');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
