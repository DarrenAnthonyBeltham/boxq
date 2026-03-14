<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $po->po_number }}</title>
    <style>
        body { font-family: sans-serif; color: #333; }
        .header { text-align: right; margin-bottom: 40px; }
        .header h1 { margin: 0; color: #1e2329; }
        .details-table, .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .details-table td { vertical-align: top; width: 50%; }
        .items-table th { background: #f8fafc; border-bottom: 2px solid #ddd; padding: 10px; text-align: left; }
        .items-table td { border-bottom: 1px solid #ddd; padding: 10px; }
        .total-row { font-weight: bold; font-size: 1.2em; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PURCHASE ORDER</h1>
        <p><strong>PO Number:</strong> {{ $po->po_number }}<br>
        <strong>Date:</strong> {{ \Carbon\Carbon::parse($po->created_at)->format('d M Y') }}</p>
    </div>

    <table class="details-table">
        <tr>
            <td>
                <strong>Billed To:</strong><br>
                BoxQ Technologies<br>
                Procurement Department
            </td>
            <td>
                <strong>Vendor:</strong><br>
                {{ $vendor->name }}<br>
                {{ $vendor->address }}<br>
                Email: {{ $vendor->email }}<br>
                Tax ID: {{ $vendor->tax_id }}<br>
                Terms: {{ $vendor->payment_terms }}
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Item Description</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($requisition->items as $item)
            <tr>
                <td>{{ $item['name'] }}</td>
                <td>{{ $item['qty'] }}</td>
                <td>{{ $requisition->currency === 'USD' ? '$' : 'Rp' }}{{ number_format($item['price'], 2) }}</td>
                <td>{{ $requisition->currency === 'USD' ? '$' : 'Rp' }}{{ number_format($item['price'] * $item['qty'], 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3" style="text-align: right;">Grand Total:</td>
                <td>{{ $requisition->currency === 'USD' ? '$' : 'Rp' }}{{ number_format($po->total_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>