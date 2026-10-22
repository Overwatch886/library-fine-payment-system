<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowing History</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <main class="container">
        <h1>Borrowing History</h1>
        <p class="results-placeholder" id="history-placeholder">Loading…</p>
        <table id="history-table" class="data-table" hidden>
            <thead>
                <tr>
                    <th>Book</th>
                    <th>Borrowed</th>
                    <th>Due</th>
                    <th>Returned</th>
                    <th>Fine</th>
                </tr>
            </thead>
            <tbody id="history-tbody"></tbody>
        </table>
    </main>
    <script src="assets/js/loans_history.js"></script>
</body>
</html>
