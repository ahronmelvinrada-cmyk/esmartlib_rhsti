<?php
require 'config.php';
require 'ui2026.php';
auth();

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

$s = $pdo->prepare("SELECT * FROM books WHERE title LIKE ? OR accession_no LIKE ? OR author LIKE ? ORDER BY title");
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
  <button type="button" class="ghost" onclick="print()"><?=ic('print')?>Print QR Codes</button>
  <span class="badge"><?=count($rows)?> title<?=count($rows)==1?'':'s'?></span>
</form>

<div class="qrgrid">
  <?php if (!$rows): ?><div class="empty"><b>No books found</b>Try a different search or add a new book above.</div><?php endif; ?>
  <?php foreach ($rows as $r):
      $pct = $r['quantity'] > 0 ? round($r['available'] / $r['quantity'] * 100) : 0;
      $tone = $pct <= 0 ? 'low' : ($pct < 50 ? 'mid' : '');
  ?>
    <div class="card qrcard">
      <div class="qr" data-code="BK:<?=h($r['accession_no'])?>"></div>
      <b><?=h($r['title'])?></b>
      <small><?=h($r['author'])?></small>
      <div class="chips">
        <span class="chip id"><?=h($r['accession_no'])?></span>
        <?php if ($r['category'] !== ''): ?><span class="chip"><?=h($r['category'])?></span><?php endif; ?>
      </div>
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
<?php
foot('<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><script>renderQR()</script>');