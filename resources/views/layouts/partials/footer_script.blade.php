<!-- jQuery first -->
<script src="{{ asset('admin/js/jquery.min.js') }}"></script>

<!-- Bootstrap JS -->
<script src="{{ asset('admin/js/bootstrap.bundle.min.js') }}"></script>

<!-- Plugins -->
<script src="{{ asset('admin/plugins/simplebar/js/simplebar.min.js') }}"></script>
<script src="{{ asset('admin/plugins/metismenu/js/metisMenu.min.js') }}"></script>
<script src="{{ asset('admin/plugins/perfect-scrollbar/js/perfect-scrollbar.js') }}"></script>

<!-- Charts only on dashboard -->
@if(request()->is('dashboard') || request()->is('*/dashboard'))
<script src="{{ asset('admin/plugins/vectormap/jquery-jvectormap-2.0.2.min.js') }}"></script>
<script src="{{ asset('admin/plugins/vectormap/jquery-jvectormap-world-mill-en.js') }}"></script>
<script src="{{ asset('admin/plugins/chartjs/js/chart.js') }}"></script>
@endif

<!-- DataTable -->
<script src="{{ asset('admin/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('admin/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
<script>
    // The list views render an empty list as one `<td colspan>` row, which
    // DataTables cannot index ("Cannot set properties of undefined (setting
    // '_DT_CellIndex')"), and the page script stopped there. That row is taken
    // out and its text becomes DataTables' own empty-table message.
    function initTable($table, options) {
        var $placeholder = $table.find('tbody > tr').filter(function () {
            var $cells = $(this).children('td');
            return $cells.length === 1 && $cells.attr('colspan');
        });
        var message = $.trim($placeholder.first().text());
        $placeholder.remove();
        $table.DataTable($.extend({}, options, message ? { language: { emptyTable: message } } : {}));
    }

    $(document).ready(function() {
        if ($('#example').length) {
            initTable($('#example'), {});
        }
        if ($('#example2').length) {
            // The DataTables Buttons extension is not bundled in
            // public/admin/plugins/datatable, so the previous `buttons: [...]`
            // config plus `table.buttons()` threw "table.buttons is not a
            // function" and aborted this handler on every records page.
            initTable($('#example2'), {
                lengthChange: false,
                pageLength: 25,
                order: []
            });
        }
    });
</script>

<!-- App JS -->
<script src="{{ asset('admin/js/app.js') }}"></script>

<script>
    if (document.querySelector('.app-container')) {
        new PerfectScrollbar(".app-container");
    }
</script>
