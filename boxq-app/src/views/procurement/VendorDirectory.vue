<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import MainLayout from '../../layouts/MainLayout.vue';

interface Vendor {
    _id?: string | { $oid: string };
    id?: string;
    name: string;
    tax_id: string;
    payment_terms: string;
    email: string;
    address: string;
}

const vendors = ref<Vendor[]>([]);
const loading = ref(true);
const showModal = ref(false);
const submitting = ref(false);

const form = ref({
    name: '',
    tax_id: '',
    payment_terms: 'Net 30',
    email: '',
    address: ''
});

const getSafeId = (v: Vendor) => {
    if (v.id) return String(v.id);
    if (v._id) return typeof v._id === 'object' ? String(v._id.$oid) : String(v._id);
    return Math.random().toString();
};

const fetchVendors = async () => {
    try {
        const response = await api.get('/vendors');
        vendors.value = response.data;
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const submitVendor = async () => {
    submitting.value = true;
    try {
        await api.post('/vendors', form.value);
        await fetchVendors();
        showModal.value = false;
        form.value = { name: '', tax_id: '', payment_terms: 'Net 30', email: '', address: '' };
    } catch (error) {
        console.error(error);
        alert('Failed to save vendor. Please check all fields.');
    } finally {
        submitting.value = false;
    }
};

onMounted(() => {
    fetchVendors();
});
</script>

<script lang="ts">
export default {
    name: 'VendorDirectory'
}
</script>

<template>
    <MainLayout>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Vendor Directory</h3>
                <p class="text-muted mb-0">Manage your approved suppliers and terms.</p>
            </div>
            <button @click="showModal = true" class="btn btn-dark shadow-sm px-4">
                <i class="fa-solid fa-plus me-2"></i>Add Vendor
            </button>
        </div>

        <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-secondary" role="status">
                <span class="visually-hidden"></span>
            </div>
        </div>

        <div v-else class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 text-uppercase small fw-bold text-secondary py-3">Vendor Name</th>
                            <th class="text-uppercase small fw-bold text-secondary py-3">Tax ID</th>
                            <th class="text-uppercase small fw-bold text-secondary py-3">Contact Email</th>
                            <th class="text-uppercase small fw-bold text-secondary py-3">Terms</th>
                            <th class="text-uppercase small fw-bold text-secondary py-3 pe-4 text-end">Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="vendor in vendors" :key="getSafeId(vendor)">
                            <td class="ps-4 fw-bold text-dark">{{ vendor.name }}</td>
                            <td class="font-monospace text-muted small">{{ vendor.tax_id }}</td>
                            <td><a :href="'mailto:' + vendor.email" class="text-decoration-none">{{ vendor.email }}</a></td>
                            <td><span class="badge bg-light text-dark border">{{ vendor.payment_terms }}</span></td>
                            <td class="text-muted small pe-4 text-end text-truncate" style="max-width: 200px;">{{ vendor.address }}</td>
                        </tr>
                        <tr v-if="vendors.length === 0">
                            <td colspan="5" class="text-center py-5 text-muted">No vendors found. Click "Add Vendor" to begin.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="showModal" class="modal-backdrop fade show" style="opacity: 0.5;"></div>
        <div v-if="showModal" class="modal d-block" tabindex="-1" @click.self="showModal = false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                        <h5 class="modal-title fw-bold">Register New Vendor</h5>
                        <button type="button" class="btn-close shadow-none" @click="showModal = false"></button>
                    </div>
                    <div class="modal-body p-4">
                        <form @submit.prevent="submitVendor">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-secondary">Company Name</label>
                                <input v-model="form.name" type="text" class="form-control" required placeholder="e.g. Dell Technologies">
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold small text-secondary">Tax ID / EIN</label>
                                    <input v-model="form.tax_id" type="text" class="form-control" required placeholder="XX-XXXXXXX">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold small text-secondary">Payment Terms</label>
                                    <select v-model="form.payment_terms" class="form-select" required>
                                        <option value="Due on Receipt">Due on Receipt</option>
                                        <option value="Net 15">Net 15</option>
                                        <option value="Net 30">Net 30</option>
                                        <option value="Net 60">Net 60</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-secondary">Billing Email</label>
                                <input v-model="form.email" type="email" class="form-control" required placeholder="billing@company.com">
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold small text-secondary">Registered Address</label>
                                <textarea v-model="form.address" class="form-control" rows="2" required placeholder="123 Corporate Blvd..."></textarea>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary py-2 fw-bold" :disabled="submitting">
                                    <span v-if="submitting" class="spinner-border spinner-border-sm me-2"></span>
                                    {{ submitting ? 'Saving...' : 'Save Vendor' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<style scoped>
.modal { background-color: rgba(0,0,0,0.1); }
</style>