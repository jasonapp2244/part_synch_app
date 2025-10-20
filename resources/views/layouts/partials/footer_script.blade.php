<!-- Bootstrap JS -->
<script src="{{ asset('admin/js/bootstrap.bundle.min.js') }}"></script>



<!-- Plugins -->
<script src="{{ asset('admin/js/jquery.min.js') }}"></script>
<script src="{{ asset('admin/plugins/simplebar/js/simplebar.min.js') }}"></script>
<script src="{{ asset('admin/plugins/metismenu/js/metisMenu.min.js') }}"></script>
<script src="{{ asset('admin/plugins/perfect-scrollbar/js/perfect-scrollbar.js') }}"></script>
<script src="{{ asset('admin/plugins/vectormap/jquery-jvectormap-2.0.2.min.js') }}"></script>
<script src="{{ asset('admin/plugins/vectormap/jquery-jvectormap-world-mill-en.js') }}"></script>
<script src="{{ asset('admin/plugins/chartjs/js/chart.js') }}"></script>
<script src="{{ asset('admin/js/index.js') }}"></script>

{{-- datatable --}}
<script src="{{ asset('admin/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('admin/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
<script>
    $(document).ready(function() {
        $('#example').DataTable();
    });
</script>
<script>
    $(document).ready(function() {
        var table = $('#example2').DataTable({
            lengthChange: false,
            buttons: ['copy', 'excel', 'pdf', 'print']
        });

        table.buttons().container()
            .appendTo('#example2_wrapper .col-md-6:eq(0)');
    });
</script>

<!-- App JS -->
<script src="{{ asset('admin/js/app.js') }}"></script>

<script>
    new PerfectScrollbar(".app-container")
</script>
