<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// Global utility for SweetAlert toasts
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
});

// Common delete function for master data
function deleteRecord(table, idCol, idVal) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This could break linked records if not handled by DB constraints!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#202227',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('table', table);
            formData.append('idCol', idCol);
            formData.append('idVal', idVal);
            
            fetch('../api/manage_settings.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    location.reload();
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            })
            .catch(err => {
                console.error('Delete error:', err);
                Swal.fire('Error', 'An unexpected error occurred during deletion.', 'error');
            });
        }
    });
}
</script>
