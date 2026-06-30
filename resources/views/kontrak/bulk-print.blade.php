<!DOCTYPE html>
<html>
<head>
    <title>Bulk Kontrak</title>
    <style>
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

@foreach($kontraks as $kontrak)

    @include('contracts.pkwt', ['record' => $kontrak])

    <div class="page-break"></div>

@endforeach

<script>
    window.print();
</script>

</body>
</html>