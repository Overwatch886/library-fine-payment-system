/*
 * Catalogue page: load categories, search books, render results, borrow.
 */

const API = 'api';

const searchForm = document.getElementById('search-form');
const searchBox = document.getElementById('book-search-box');
const categoryFilter = document.getElementById('category-filter');
const resultsContainer = document.getElementById('book-results');

/* ---------- helpers ---------- */

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function showPlaceholder(msg) {
    resultsContainer.innerHTML = `<p class="results-placeholder">${escapeHtml(msg)}</p>`;
}

/* ---------- categories ---------- */

async function loadCategories() {
    try {
        const res = await fetch(`${API}/books/categories.php`);
        const json = await res.json();
        if (!json.success) return;

        json.data.forEach((cat) => {
            const value = typeof cat === 'string' ? cat : cat.name;
            const opt = document.createElement('option');
            opt.value = value;
            opt.textContent = value;
            categoryFilter.appendChild(opt);
        });
    } catch (err) {
        console.error('Failed to load categories:', err);
    }
}

/* ---------- search ---------- */

async function searchBooks() {
    const q = searchBox.value.trim();
    const category = categoryFilter.value;

    const params = new URLSearchParams();
    if (q) params.set('q', q);
    if (category) params.set('category', category);

    try {
        const res = await fetch(`${API}/books/search.php?${params.toString()}`);
        const json = await res.json();

        if (!json.success) {
            showPlaceholder(json.message || 'Search failed.');
            return;
        }

        renderResults(json.data);
    } catch (err) {
        console.error('Search error:', err);
        showPlaceholder('Something went wrong. Please try again.');
    }
}

/* ---------- render ---------- */

function renderResults(books) {
    if (!books || books.length === 0) {
        showPlaceholder('No books found. Try a different search.');
        return;
    }

    resultsContainer.innerHTML = books.map((book) => {
        const available = book.available_copies > 0;
        return `
            <div class="book-card">
                <h3>${escapeHtml(book.title)}</h3>
                <p class="book-author">${escapeHtml(book.author)}</p>
                <p class="book-category">${escapeHtml(book.category)}</p>
                <p class="book-copies">Available: ${book.available_copies} / ${book.total_copies}</p>
                ${available
                    ? `<button class="buttons borrow-btn" data-book-id="${book.id}">Borrow</button>`
                    : '<span class="book-unavailable">All copies borrowed</span>'}
            </div>`;
    }).join('');

    resultsContainer.querySelectorAll('.borrow-btn').forEach((btn) => {
        btn.addEventListener('click', () => borrowBook(Number(btn.dataset.bookId)));
    });
}

/* ---------- borrow ---------- */

async function borrowBook(bookId) {
    try {
        const res = await fetch(`${API}/loans/borrow.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ book_id: bookId }),
        });
        const json = await res.json();

        alert(json.message);

        if (json.success) {
            searchBooks(); // refresh to update available copies
        }
    } catch (err) {
        console.error('Borrow error:', err);
        alert('Something went wrong. Please try again.');
    }
}

/* ---------- events ---------- */

searchForm.addEventListener('submit', (e) => {
    e.preventDefault();
    searchBooks();
});

/* ---------- init ---------- */

loadCategories();
searchBooks();
