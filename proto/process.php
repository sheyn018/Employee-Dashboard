<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $basic = floatval($_POST['basic']);
    $deductions = floatval($_POST['deductions']);
    
    $net = $basic - $deductions;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip for <?php echo htmlspecialchars($name); ?></title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .payslip { max-width: 500px; margin: auto; border: 1px solid #ccc; padding: 20px; }
        h2 { text-align: center; }
        table { width: 100%; margin-top: 20px; border-collapse: collapse; }
        td { padding: 10px; border: 1px solid #ddd; }
        .text-right { text-align: right; }
        button { margin-top: 20px; padding: 10px 20px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="payslip">
        <h2>Payslip</h2>
        <table>
            <tr>
                <td>Employee Name:</td>
                <td><?php echo htmlspecialchars($name); ?></td>
            </tr>
            <tr>
                <td>Basic Salary:</td>
                <td class="text-right">₱<?php echo number_format($basic, 2); ?></td>
            </tr>
            <tr>
                <td>Deductions:</td>
                <td class="text-right">₱<?php echo number_format($deductions, 2); ?></td>
            </tr>
            <tr>
                <td><strong>Net Pay:</strong></td>
                <td class="text-right"><strong>₱<?php echo number_format($net, 2); ?></strong></td>
            </tr>
        </table>
        <button onclick="window.print()">Print Payslip</button>
    </div>
</body>
</html>
