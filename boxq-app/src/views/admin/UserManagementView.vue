<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useToast } from 'vue-toastification';
import axios from 'axios';
import api from '../../services/api';
import MainLayout from '../../layouts/MainLayout.vue';

interface User {
    _id: string;
    name: string;
    email: string;
    role: string;
    department: string;
    created_at: string;
}

const toast = useToast();
const users = ref<User[]>([]);
const loading = ref(true);
const searchQuery = ref('');

const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const showModal = ref(false);
const isSubmitting = ref(false);
const isEditMode = ref(false);
const editingUserId = ref<string | null>(null);

const form = ref({
    name: '',
    email: '',
    password: '',
    role: 'employee',
    department: ''
});

const departments = ['Engineering', 'Marketing', 'Sales', 'HR', 'Finance', 'IT', 'Operations', 'External Vendor'];

const fetchUsers = async (page = 1) => {
    loading.value = true;
    try {
        const response = await api.get('/users', { params: { page, search: searchQuery.value } });
        users.value = response.data.data;
        pagination.value = { current_page: response.data.current_page, last_page: response.data.last_page, total: response.data.total };
    } catch (error) {
        toast.error("Failed to load users.");
    } finally {
        loading.value = false;
    }
};

onMounted(() => fetchUsers());

watch(searchQuery, () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => fetchUsers(1), 500);
});

watch(() => form.value.role, (newRole) => {
    if (newRole === 'vendor') {
        form.value.department = 'External Vendor';
    }
});

const openCreateModal = () => {
    isEditMode.value = false;
    editingUserId.value = null;
    form.value = { name: '', email: '', password: '', role: 'employee', department: '' };
    showModal.value = true;
};

const openEditModal = (user: User) => {
    isEditMode.value = true;
    editingUserId.value = user._id;
    form.value = { 
        name: user.name, 
        email: user.email, 
        password: '', 
        role: user.role, 
        department: user.department 
    };
    showModal.value = true;
};

const saveUser = async () => {
    if (!form.value.name || !form.value.email || !form.value.department) {
        toast.warning('Please fill in all required fields.');
        return;
    }

    if (!isEditMode.value && !form.value.password) {
        toast.warning('Password is required for new users.');
        return;
    }

    isSubmitting.value = true;
    try {
        if (isEditMode.value && editingUserId.value) {
            await api.put(`/users/${editingUserId.value}`, form.value);
            toast.success("User updated successfully!");
        } else {
            await api.post('/users', form.value);
            toast.success("User created successfully!");
        }
        showModal.value = false;
        fetchUsers(pagination.value.current_page);
    } catch (error: unknown) {
        if (axios.isAxiosError(error)) {
            toast.error(error.response?.data?.message || "Failed to save user. Check for duplicate emails.");
        } else {
            toast.error("Failed to save user.");
        }
    } finally {
        isSubmitting.value = false;
    }
};

const getRoleBadge = (role: string) => {
    const map: Record<string, string> = {
        'admin': 'bg-danger', 'finance': 'bg-success', 'manager': 'bg-primary', 'vendor': 'bg-dark', 'employee': 'bg-secondary'
    };
    return map[role] || 'bg-secondary';
};

const formatDate = (dateString: string) => new Date(dateString).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
</script>

<script lang="ts"> export default { name: 'UserManagementView' } </script>

<template>
    <MainLayout>
        <div class="mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 fade-in">
            <div>
                <h3 class="fw-bold text-dark mb-1">User Directory</h3>
                <p class="text-muted mb-0">Manage system access, roles, and vendor accounts.</p>
            </div>
            <button @click="openCreateModal" class="btn btn-dark shadow-sm fw-bold px-4">
                <i class="fa-solid fa-user-plus me-2"></i>Add New User
            </button>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden fade-in">
            <div class="card-header bg-white border-bottom p-3">
                <div class="input-group" style="max-width: 400px;">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input v-model="searchQuery" type="text" class="form-control border-start-0 bg-light" placeholder="Search users by name, email, dept...">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small fw-bold text-uppercase">
                        <tr>
                            <th class="py-3 px-4 border-0">Name</th>
                            <th class="py-3 border-0">Email Account</th>
                            <th class="py-3 border-0">Role</th>
                            <th class="py-3 border-0">Department</th>
                            <th class="py-3 border-0">Date Added</th>
                            <th class="py-3 px-4 border-0 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody v-if="loading">
                        <tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></td></tr>
                    </tbody>
                    <tbody v-else-if="users.length === 0">
                        <tr><td colspan="6" class="text-center py-5 text-muted">No users found.</td></tr>
                    </tbody>
                    <tbody v-else>
                        <tr v-for="u in users" :key="u._id" class="border-bottom hover-row">
                            <td class="px-4 py-3 fw-bold text-dark d-flex align-items-center gap-3">
                                <div class="bg-light text-secondary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px;">
                                    {{ u.name.charAt(0).toUpperCase() }}
                                </div>
                                {{ u.name }}
                            </td>
                            <td class="py-3 text-muted">{{ u.email }}</td>
                            <td class="py-3"><span class="badge rounded-pill px-3 py-2 text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;" :class="getRoleBadge(u.role)">{{ u.role }}</span></td>
                            <td class="py-3"><span class="badge bg-light text-dark border">{{ u.department }}</span></td>
                            <td class="py-3 text-muted small">{{ formatDate(u.created_at) }}</td>
                            <td class="px-4 py-3 text-end">
                                <button @click="openEditModal(u)" class="btn btn-light border btn-sm shadow-sm px-3 rounded-pill text-dark hover-primary transition-all">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div v-if="!loading && pagination.last_page > 1" class="card-footer bg-white border-top p-3 d-flex justify-content-between align-items-center">
                <span class="text-muted small">Page <strong>{{ pagination.current_page }}</strong> of {{ pagination.last_page }}</span>
                <div class="btn-group shadow-sm">
                    <button @click="fetchUsers(pagination.current_page - 1)" class="btn btn-light border btn-sm" :disabled="pagination.current_page === 1"><i class="fa-solid fa-chevron-left"></i></button>
                    <button @click="fetchUsers(pagination.current_page + 1)" class="btn btn-light border btn-sm" :disabled="pagination.current_page === pagination.last_page"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <div v-if="showModal" class="modal-backdrop fade show" style="opacity: 0.5;"></div>
        <div v-if="showModal" class="modal fade show d-block" tabindex="-1" @click.self="showModal = false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden modal-zoom">
                    <div class="modal-header bg-dark text-white border-0 py-3">
                        <h5 class="modal-title fw-bold">
                            <i class="fa-solid fa-user-shield me-2"></i>
                            {{ isEditMode ? 'Edit User Profile' : 'Provision New Account' }}
                        </h5>
                        <button type="button" class="btn-close btn-close-white shadow-none" @click="showModal = false"></button>
                    </div>
                    <div class="modal-body p-4 bg-light">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label small fw-bold text-secondary">Full Name / Vendor Name</label>
                                <input type="text" v-model="form.name" class="form-control shadow-sm border-0" placeholder="e.g. John Doe or CoreDigital">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-bold text-secondary">Email Address (Login ID)</label>
                                <input type="email" v-model="form.email" class="form-control shadow-sm border-0" placeholder="email@company.com">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-bold text-secondary">
                                    {{ isEditMode ? 'Change Password (Leave blank to keep current)' : 'Temporary Password' }}
                                </label>
                                <input type="text" v-model="form.password" class="form-control shadow-sm border-0" :placeholder="isEditMode ? 'Leave blank to skip' : 'Min 8 characters'">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">System Role</label>
                                <select v-model="form.role" class="form-select shadow-sm border-0">
                                    <option value="employee">Employee (Requester)</option>
                                    <option value="manager">Manager (Approver)</option>
                                    <option value="finance">Finance (Payer)</option>
                                    <option value="admin">System Admin</option>
                                    <option value="vendor">External Vendor</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Department</label>
                                <select v-model="form.department" class="form-select shadow-sm border-0" :disabled="form.role === 'vendor'">
                                    <option value="" disabled>Select Dept...</option>
                                    <option v-for="dept in departments" :key="dept" :value="dept">{{ dept }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-3 bg-white">
                        <button type="button" class="btn btn-light rounded-pill px-4" @click="showModal = false" :disabled="isSubmitting">Cancel</button>
                        <button type="button" class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm" @click="saveUser" :disabled="isSubmitting">
                            <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2"></span>
                            <span v-else><i class="fa-solid" :class="isEditMode ? 'fa-save' : 'fa-plus'"></i> {{ isEditMode ? 'Save Changes' : 'Create User' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.fade-in { animation: fadeIn 0.4s ease-in-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.hover-row:hover { background-color: #f8f9fa !important; }
.modal-zoom { animation: zoomIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
@keyframes zoomIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
.form-control:focus, .form-select:focus { box-shadow: 0 0 0 0.25rem rgba(33, 37, 41, 0.15) !important; }
.hover-primary:hover { background-color: #0d6efd !important; color: white !important; border-color: #0d6efd !important; }
.transition-all { transition: all 0.2s ease-in-out; }
</style>