<?php
require 'config.php';
require 'Ui2026.PHP';
auth();
ensure_book_request_schema($pdo);

// 1. Handle Single Book Addition
if (isset($_POST['add'])) {
    try {
        $n = max(1, (int)$_POST['quantity']);
        $pdo->prepare("INSERT INTO books(accession_no, title, author, publisher, category, quantity, available) VALUES(?,?,?,?,?,?,?)")
            ->execute([trim($_POST['accession_no']), $_POST['title'], $_POST['author'], $_POST['publisher'], $_POST['category'], $n, $n]);
        $m = 'Book added.';
    } catch (Exception $e) {
        $m = 'Error: Accession number already exists.';
    }
}

// 2. Handle CSV Import
if (isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileExtension === 'csv') {
            if (($handle = fopen($fileTmpPath, 'r')) !== FALSE) {
                $header = fgetcsv($handle);

                $successCount = 0;
                $errorCount = 0;

                $stmt = $pdo->prepare("INSERT INTO books(accession_no, title, author, publisher, category, quantity, available) VALUES(?,?,?,?,?,?,?)");

                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    // Columns: accession_no, title, author, publisher, category, quantity
                    if (count($data) >= 5) {
                        $accession = trim($data[0]);
                        $title = trim($data[1]);
                        $author = trim($data[2]);
                        $publisher = trim($data[3]);
                        $category = trim($data[4]);
                        $quantity = isset($data[5]) ? max(1, (int)$data[5]) : 1;

                        try {
                            $stmt->execute([$accession, $title, $author, $publisher, $category, $quantity, $quantity]);
                            $successCount++;
                        } catch (Exception $e) {
                            $errorCount++;
                        }
                    }
                }
                fclose($handle);
                $m = "CSV Imported! Success: $successCount, Skipped/Duplicates: $errorCount";
            } else {
                $m = 'Error: Cannot read the uploaded CSV file.';
            }
        } else {
            $m = 'Error: Please upload a valid .csv file.';
        }
    } else {
        $m = 'Error: Please select a CSV file to upload.';
    }
}

// 3. Handle Book Deletion
if (isset($_POST['del'])) {
    auth(['admin']);
    try {
        $pdo->prepare("DELETE FROM books WHERE id=?")->execute([$_POST['del']]);
        $m = 'Book deleted.';
    } catch (Exception $e) {
        $m = 'Cannot delete: book has transactions.';
    }
}

// 4. Handle bulk book deletion
if (isset($_POST['bulk_del'])) {
    auth(['admin']);
    $selectedIds = isset($_POST['delete_ids']) && is_array($_POST['delete_ids']) ? $_POST['delete_ids'] : [];
    $bookIds = [];
    foreach ($selectedIds as $selectedId) {
        $bookId = filter_var($selectedId, FILTER_VALIDATE_INT);
        if ($bookId !== false && $bookId > 0) $bookIds[$bookId] = $bookId;
    }

    if (!$bookIds) {
        $m = 'Select at least one book to delete.';
    } else {
        $deletedCount = 0;
        $skippedCount = 0;
        $deleteBook = $pdo->prepare("DELETE FROM books WHERE id=?");
        foreach ($bookIds as $bookId) {
            try {
                $deleteBook->execute([$bookId]);
                if ($deleteBook->rowCount() === 1) $deletedCount++;
                else $skippedCount++;
            } catch (Exception $e) {
                error_log('Bulk book deletion failed for book '.$bookId.': '.$e->getMessage());
                $skippedCount++;
            }
        }
        $m = 'Deleted '.$deletedCount.' book'.($deletedCount===1?'':'s').'.';
        if ($skippedCount) $m .= ' '.$skippedCount.' could not be deleted because they have transaction records or another database restriction.';
    }
}

$s = $pdo->prepare("SELECT b.id,b.accession_no,b.title,b.author,b.publisher,b.category,b.quantity,
    GREATEST(0,b.available-(SELECT COUNT(*) FROM book_requests r WHERE r.book_id=b.id AND r.status='approved')) AS available
    FROM books b WHERE b.title LIKE ? OR b.accession_no LIKE ? OR b.author LIKE ? ORDER BY b.title");
$l = '%'.($_GET['q']??'').'%';
$s->execute([$l, $l, $l]);
$rows = $s->fetchAll();

head('Books Catalog');
ui_css();
?>
<div class="pg">

<?php if (!empty($m)) echo '<div class="note">'.ic('info').'<span>'.h($m).'</span></div>'; ?>

<!-- Add Single Book -->
<form method="post" class="card">
  <div class="ph"><span class="pico"><?=ic('book')?></span><div><h3>Add book</h3><p>Add a title to the catalog and generate its QR code.</p></div></div>
  <div class="fgrid">
    <input name="accession_no" placeholder="Accession / Book ID" required>
    <input name="title" placeholder="Title" required>
    <input name="author" placeholder="Author">
    <input name="publisher" placeholder="Publisher">
    <input name="category" placeholder="Category">
    <input name="quantity" type="number" min="1" value="1" title="Quantity">
  </div>
  <div class="fact"><button name="add" value="1"><?=ic('plus')?>Add Book</button></div>
</form>

<!-- Import CSV -->
<form method="post" enctype="multipart/form-data" class="card">
  <div class="ph"><span class="pico"><?=ic('upload')?></span><div><h3>Import from CSV</h3><p>Add many books at once.</p></div></div>
  <div class="importrow">
    <input type="file" name="csv_file" accept=".csv" required>
    <button name="import_csv" value="1"><?=ic('upload')?>Import CSV</button>
  </div>
  <p class="hint">Columns (with header row): accession_no, title, author, publisher, category, quantity. Duplicate accession numbers are skipped.</p>
</form>

<!-- Search & Print -->
<form class="bar">
  <div class="sw"><?=ic('search')?><input name="q" placeholder="Search title, author or accession no..." value="<?=h($_GET['q']??'')?>"></div>
  <button><?=ic('search')?>Search</button>
  <button type="button" class="ghost" onclick="printBookRecords()"><?=ic('print')?>Print Book Records</button>
  <button type="button" class="ghost" onclick="print()"><?=ic('print')?>Print QR Codes</button>
  <span class="badge"><?=count($rows)?> title<?=count($rows)==1?'':'s'?></span>
</form>

<section class="book-record-print" aria-hidden="true">
  <header>
    <h1><?=h(setting($pdo,'library_name','eSmartlib'))?></h1>
    <p><?=h(setting($pdo,'school_name','RHSTI Library'))?></p>
    <h2>Book Inventory Records</h2>
    <p>Printed <?=h(date('F j, Y'))?> · <?=count($rows)?> title<?=count($rows)==1?'':'s'?></p>
  </header>
  <table>
    <thead><tr><th>#</th><th>Accession / Book ID</th><th>Title</th><th>Author</th><th>Publisher</th><th>Category</th><th>Total Copies</th><th>Available</th></tr></thead>
    <tbody>
      <?php foreach($rows as $i=>$r):?>
        <tr>
          <td><?=$i+1?></td>
          <td><?=h($r['accession_no'])?></td>
          <td><?=h($r['title'])?></td>
          <td><?=h($r['author']?:'-')?></td>
          <td><?=h($r['publisher']?:'-')?></td>
          <td><?=h($r['category']?:'-')?></td>
          <td><?=h($r['quantity'])?></td>
          <td><?=h($r['available'])?></td>
        </tr>
      <?php endforeach;?>
      <?php if(!$rows):?><tr><td colspan="8">No books found.</td></tr><?php endif;?>
    </tbody>
  </table>
</section>

<?php if ($_SESSION['u']['role'] === 'admin'):?>
<div class="bulk-actions noprint">
  <label><input type="checkbox" id="select-all-books"> Select all books</label>
  <span id="selected-book-count">0 selected</span>
  <form method="post" id="bulk-delete-form" onsubmit="return confirmSelectedBookDeletion()">
    <button class="danger" name="bulk_del" value="1"><?=ic('trash')?>Delete selected</button>
  </form>
</div>
<?php endif;?>

<div class="qrgrid">
  <?php if (!$rows): ?><div class="empty"><b>No books found</b>Try a different search or add a new book above.</div><?php endif; ?>
  <?php foreach ($rows as $r):
      $pct = $r['quantity'] > 0 ? round($r['available'] / $r['quantity'] * 100) : 0;
      $tone = $pct <= 0 ? 'low' : ($pct < 50 ? 'mid' : '');
  ?>
    <div class="card qrcard">
      <div class="qr" data-code="BK:<?=h($r['accession_no'])?>"></div>
      <?php if ($_SESSION['u']['role'] === 'admin'):?>
        <label class="book-select-label noprint"><input class="book-select" type="checkbox" name="delete_ids[]" value="<?=$r['id']?>" form="bulk-delete-form"><span>Select</span></label>
      <?php endif;?>
      <b><?=h($r['title'])?></b>
      <small><?=h($r['author'])?></small>
      <div class="chips">
        <span class="chip id"><?=h($r['accession_no'])?></span>
        <?php if ($r['category'] !== ''): ?><span class="chip"><?=h($r['category'])?></span><?php endif; ?>
      </div>
      <details class="book-view">
        <summary>View book</summary>
        <dl>
          <div><dt>Accession / Book ID</dt><dd><?=h($r['accession_no'])?></dd></div>
          <div><dt>Author</dt><dd><?=h($r['author']?:'Not specified')?></dd></div>
          <div><dt>Publisher</dt><dd><?=h($r['publisher']?:'Not specified')?></dd></div>
          <div><dt>Category</dt><dd><?=h($r['category']?:'Not specified')?></dd></div>
          <div><dt>Copies</dt><dd><?=h($r['quantity'])?> total, <?=h($r['available'])?> available</dd></div>
        </dl>
      </details>
      <div class="meter <?=$tone?>" style="margin-top:6px"><i style="width:<?=$pct?>%"></i></div>
      <small>Available <b><?=$r['available']?></b>/<?=$r['quantity']?></small>
      <?php if ($_SESSION['u']['role'] == 'admin'): ?>
        <form method="post" class="noprint" onsubmit="return confirm('Delete?')">
          <button class="danger" name="del" value="<?=$r['id']?>"><?=ic('trash')?>Delete</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

</div>
<style>
.book-record-print{display:none}
.pg .bulk-actions{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:12px 16px;border:1px solid var(--line);border-radius:14px;background:var(--card)}
.pg .bulk-actions>label{display:flex;align-items:center;gap:8px;font-weight:700}
.pg .bulk-actions input[type=checkbox]{width:18px;height:18px;margin:0;accent-color:var(--g)}
.pg .bulk-actions form{margin-left:auto}
.pg .bulk-actions button{background:var(--redbg);color:var(--red);box-shadow:none}
.pg .bulk-actions button:hover{background:#fbd3cf}
.pg #selected-book-count{color:var(--mute);font-size:13px}
.pg .qrcard .book-select-label{position:absolute;top:12px;left:12px;display:flex;align-items:center;gap:5px;color:var(--mute);font-size:12px;font-weight:700}
.pg .qrcard .book-select{width:18px;height:18px;margin:0;accent-color:var(--g)}
.pg .book-view{width:100%;text-align:left;border-top:1px solid var(--line);padding-top:10px}
.pg .book-view summary{cursor:pointer;color:var(--g);font-size:14px;font-weight:800}
.pg .book-view dl{display:grid;gap:8px;margin:12px 0 2px;text-align:left}
.pg .book-view dl>div{display:grid;grid-template-columns:94px minmax(0,1fr);gap:8px}
.pg .book-view dt{color:var(--mute);font-size:12px;font-weight:700}
.pg .book-view dd{margin:0;color:var(--ink);font-size:13px;overflow-wrap:anywhere}
[data-theme="dark"] .pg .book-view{border-color:var(--line)}
@media print{
  @page{size:landscape;margin:12mm}
  body.printing-book-records *{visibility:hidden!important}
  body.printing-book-records .book-record-print,
  body.printing-book-records .book-record-print *{visibility:visible!important}
  body.printing-book-records .book-record-print{display:block!important;position:absolute;inset:0 auto auto 0;width:100%;padding:0 12px}
  body.printing-book-records .book-record-print header{text-align:center;margin-bottom:20px}
  body.printing-book-records .book-record-print h1{font-size:20px;margin:0}
  body.printing-book-records .book-record-print h2{font-size:17px;margin:12px 0 4px}
  body.printing-book-records .book-record-print p{margin:4px 0;font-size:11px}
  body.printing-book-records .book-record-print table{width:100%;border-collapse:collapse;font-size:10px}
  body.printing-book-records .book-record-print th,
  body.printing-book-records .book-record-print td{border:1px solid #555;padding:6px;text-align:left;overflow-wrap:anywhere}
  body.printing-book-records .book-record-print th{background:#e8eeea!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  body.printing-book-records .book-record-print tr{break-inside:avoid}
}
</style>
<?php
foot('<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><script>
renderQR();
function printBookRecords(){document.body.classList.add("printing-book-records");window.print()}
window.addEventListener("afterprint",function(){document.body.classList.remove("printing-book-records")});
var bookChecks=document.querySelectorAll(".book-select");
var selectAllBooks=document.getElementById("select-all-books");
var selectedBookCount=document.getElementById("selected-book-count");
function updateSelectedBookCount(){
  var selected=document.querySelectorAll(".book-select:checked").length;
  if(selectedBookCount)selectedBookCount.textContent=selected+" selected";
  if(selectAllBooks)selectAllBooks.checked=bookChecks.length>0&&selected===bookChecks.length;
}
if(selectAllBooks)selectAllBooks.addEventListener("change",function(){
  bookChecks.forEach(function(box){box.checked=selectAllBooks.checked});
  updateSelectedBookCount();
});
bookChecks.forEach(function(box){box.addEventListener("change",updateSelectedBookCount)});
function confirmSelectedBookDeletion(){
  var selected=document.querySelectorAll(".book-select:checked").length;
  if(!selected){alert("Select at least one book first.");return false}
  return confirm("Delete "+selected+" selected book"+(selected===1?"":"s")+"? Books with transaction records cannot be deleted.");
}
</script>');
