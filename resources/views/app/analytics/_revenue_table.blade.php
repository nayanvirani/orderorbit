@if (! $rows)
    <s-paragraph><span class="oo-muted">No orders in this period.</span></s-paragraph>
@else
    <table class="oo-table stack an-break">
        <thead><tr><th>{{ $label }}</th><th>Orders</th><th>Revenue</th><th>Share</th><th class="an-bar-col"></th></tr></thead>
        <tbody>
            @foreach ($rows as $key => $row)
                <tr>
                    <td data-label="{{ $label }}">{{ $key === 'unknown' ? 'Unknown (before source tracking)' : $key }}</td>
                    <td data-label="Orders">{{ number_format($row['orders']) }}</td>
                    <td data-label="Revenue">{{ $money($row['revenue']) }}</td>
                    <td data-label="Share">{{ $total ? round($row['revenue'] / $total * 100) : 0 }}%</td>
                    <td class="an-bar-col"><span class="an-bar"><i style="width:{{ $total ? $row['revenue'] / $total * 100 : 0 }}%"></i></span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
