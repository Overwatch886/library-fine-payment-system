const API = '/api';

/** Fetch a JSON endpoint and return the parsed body. */
async function fetchJson(url, options = {}) {
    const res = await fetch(url, {
        headers: { 'Content-Type': 'application/json' },
        ...options,
    });
    return res.json();
}

/** Populate the stat cards from /admin/stats.php */
async function loadStats() {
    try {
        const json = await fetchJson(`${API}/admin/stats.php`);
        if (!json.success) return;
        const d = json.data;
        // TODO: set each stat-value element's textContent from d
        // e.g. document.getElementById('stat-total-books').textContent = d.total_books;
    } catch (err) {
        console.error('Stats failed:', err);
    }
}

/** Load and render the loans table. Reads filter inputs. */
async function loadLoans() {
    const status = document.getElementById('loan-status').value;
    const q = document.getElementById('loan-search').value.trim();

    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (q) params.set('q', q);

    try {
        const json = await fetchJson(`${API}/admin/loans.php?${params.toString()}`);
        if (!json.success) return;

        const tbody = document.getElementById('loans-tbody');
        const loans = json.data;

        if (!loans.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="results-placeholder">No loans found.</td></tr>';
            return;
        }

        // TODO: build <tr> rows from each loan object
        // Fields available: student_name, matric_number, book_title,
        //   borrow_date, due_date, return_date, is_overdue, days_overdue,
        //   fine_amount, fine_status, fine_so_far
    } catch (err) {
        console.error('Loans failed:', err);
    }
}

// Wire up the filter button
document.getElementById('loan-filter-btn').addEventListener('click', loadLoans);

// Initial load
loadStats();
loadLoans();