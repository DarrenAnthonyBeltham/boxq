<script setup lang="ts">
import { reactive, computed } from 'vue';
import api from '../../services/api';
import { useRouter } from 'vue-router';
import MainLayout from '../../layouts/MainLayout.vue';

const router = useRouter();

const form = reactive({
    requester: 'Darren Beltham',
    department: 'Engineering',
    items: [
        { name: '', price: 0, qty: 1 }
    ]
});

const grandTotal = computed(() => {
    return form.items.reduce((sum, item) => sum + (item.price * item.qty), 0);
});

const addItem = () => {
    form.items.push({ name: '', price: 0, qty: 1 });
};

const removeItem = (index: number) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const submitForm = async () => {
    try {
        const payload = {
            ...form,
            total_price: grandTotal.value
        };
        await api.post('/requisitions', payload);
        router.push('/');
    } catch (error) {
        alert("Failed to submit request.");
    }
};
</script>

<script lang="ts">
export default {
    name: 'RequisitionCreate'
}
</script>

<template>
    <MainLayout>
        <div class="d-flex align-items-center mb-4">
            <RouterLink to="/" class="btn btn-outline-secondary me-3 btn-sm">
                <i class="fa-solid fa-arrow-left"></i>
            </RouterLink>
            <div>
                <h3 class="fw-bold mb-0">Create Request</h3>
                <p class="text-muted mb-0 small">Submit a new purchase requisition for approval.</p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body p-4">
                        <form @submit.prevent="submitForm">
                            <h6 class="text-uppercase text-secondary fw-bold small mb-3">General Information</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Requester Name</label>
                                    <input v-model="form.requester" class="form-control bg-light" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Department</label>
                                    <select v-model="form.department" class="form-select">
                                        <option>Engineering</option>
                                        <option>Marketing</option>
                                        <option>Finance</option>
                                        <option>HR</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3 pt-3 border-top">
                                <h6 class="text-uppercase text-secondary fw-bold small mb-0">Line Items</h6>
                                <button type="button" @click="addItem" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-plus me-1"></i> Add Item
                                </button>
                            </div>

                            <div class="table-responsive mb-4">
                                <table class="table table-bordered align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 45%">Description</th>
                                            <th style="width: 20%">Price ($)</th>
                                            <th style="width: 15%">Qty</th>
                                            <th>Total</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(item, index) in form.items" :key="index">
                                            <td><input v-model="item.name" class="form-control form-control-sm" placeholder="Item name" required></td>
                                            <td><input v-model="item.price" type="number" step="0.01" class="form-control form-control-sm" required></td>
                                            <td><input v-model="item.qty" type="number" min="1" class="form-control form-control-sm" required></td>
                                            <td class="fw-bold text-end">${{ (item.price * item.qty).toFixed(2) }}</td>
                                            <td class="text-center">
                                                <button type="button" @click="removeItem(index)" class="btn btn-link text-danger p-0">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="bg-light p-3 rounded d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted small d-block">Total Estimated Cost</span>
                                    <span class="h4 fw-bold text-dark mb-0">${{ grandTotal.toFixed(2) }}</span>
                                </div>
                                <button type="submit" class="btn btn-dark px-4">
                                    Submit Request <i class="fa-solid fa-paper-plane ms-2"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card bg-info bg-opacity-10 border-info border-opacity-25 shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold text-info-emphasis"><i class="fa-solid fa-circle-info me-2"></i>Guidelines</h6>
                        <ul class="small text-secondary ps-3 mb-0">
                            <li class="mb-1">Requests under $500 are auto-approved.</li>
                            <li class="mb-1">Hardware requests require IT manager approval.</li>
                            <li>Please attach vendor quotes for items over $1,000.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>