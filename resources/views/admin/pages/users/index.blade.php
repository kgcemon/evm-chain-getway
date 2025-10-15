@extends('admin.layouts.app')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">All Users</h4>
        </div>

        <div class="card-body table-responsive">
            <table class="table table-striped table-hover">
                <thead class="thead-dark">
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($users as $index => $user)
                    <tr>
                        <td>{{ $index + $users->firstItem() }}</td>
                        <td>{{ $user->created_at->format('Y-m-d') }}</td>
                        <td class="user-name">{{ $user->name }}</td>
                        <td class="user-email">{{ $user->email }}</td>
                        <td>
                        <span class="badge {{ $user->is_block ? 'bg-danger' : 'bg-success' }}">
                            {{ $user->is_block ? 'Blocked' : 'Active' }}
                        </span>
                        </td>
                        <td>
                            <button type="button"
                                    class="btn btn-sm btn-primary editUserBtn"
                                    data-id="{{ $user->id }}"
                                    data-name="{{ $user->name }}"
                                    data-email="{{ $user->email }}"
                                    data-block="{{ $user->is_block }}">
                                <i class="fas fa-edit"></i> Manage
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center">No users found.</td></tr>
                @endforelse
                </tbody>
            </table>

            <div class="mt-3">
                {{ $users->links('admin.layouts.partials.__pagination') }}
            </div>
        </div>
    </div>

    <!-- ✅ Edit Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form id="editUserForm">
                @csrf
                @method('PUT')
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" id="editUserId">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" id="editUserName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" id="editUserEmail" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select id="editUserStatus" class="form-select">
                                <option value="0">Active</option>
                                <option value="1">Blocked</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>



    {{-- ✅ Script --}}
    <script>
        $(document).ready(function () {
            let editModal; // store modal instance globally

            // 🟢 Open Modal with Bootstrap 5 API
            $(document).on('click', '.editUserBtn', function () {
                $('#editUserId').val($(this).data('id'));
                $('#editUserName').val($(this).data('name'));
                $('#editUserEmail').val($(this).data('email'));
                $('#editUserStatus').val($(this).data('block'));

                const modalEl = document.getElementById('editUserModal');
                editModal = new bootstrap.Modal(modalEl);
                editModal.show();
            });

            // 🟢 Submit Form via AJAX
            $('#editUserForm').on('submit', function (e) {
                e.preventDefault();
                const userId = $('#editUserId').val();

                const formData = {
                    _token: '{{ csrf_token() }}',
                    _method: 'PUT',
                    name: $('#editUserName').val(),
                    email: $('#editUserEmail').val(),
                    is_block: $('#editUserStatus').val(),
                };

                $.ajax({
                    url: `/admin/users/${userId}`,
                    type: 'POST',
                    data: formData,
                    beforeSend: function () {
                        $('#editUserForm button[type=submit]').html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
                    },
                    success: function (res) {
                        $('#editUserForm button[type=submit]').html('Save Changes').prop('disabled', false);
                        editModal.hide();

                        Swal.fire({
                            icon: 'success',
                            title: 'Updated!',
                            text: 'User details updated successfully!',
                            timer: 2000,
                            showConfirmButton: false
                        });

                        // 🟢 Update table instantly
                        const row = $(`button[data-id='${userId}']`).closest('tr');
                        row.find('.user-name').text(formData.name);
                        row.find('.user-email').text(formData.email);

                        const badge = row.find('span.badge');
                        if (formData.is_block == 1) {
                            badge.removeClass('bg-success').addClass('bg-danger').text('Blocked');
                        } else {
                            badge.removeClass('bg-danger').addClass('bg-success').text('Active');
                        }

                        // Update button data for next edit
                        row.find('.editUserBtn')
                            .data('name', formData.name)
                            .data('email', formData.email)
                            .data('block', formData.is_block);
                    },
                    error: function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Something went wrong. Try again.'
                        });
                        $('#editUserForm button[type=submit]').html('Save Changes').prop('disabled', false);
                    }
                });
            });
        });
    </script>
@endsection
