<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import api from '../../services/api';
import MainLayout from '../../layouts/MainLayout.vue';

interface Product {
    _id?: string;
    id?: string;
    name: string;
    sku: string;
    price: number;
}

const router = useRouter();

const currentUser = ref({
    name: 'Loading...',
    department: 'Loading...'
});

const products = ref<Product[]>([]);

const form = reactive({
    justification: '',
    items: [
        { productId: '', name: '', price: 0, qty: 1, isCustom: false }
    ]
});

onMounted(async () => {
    const userData = localStorage.getItem('user');
    if (userData) {
        const user = JSON.parse(userData);
        currentUser.value.name = user.name;
        currentUser.value.department = user.department;
    }

    try {
        const response = await api.get('/products');
        products.value = response.data;
    } catch (error) {
        console.error(error);
    }
});

const handleProductSelect = (index: number) => {
    const item = form.items[index];
    if (!item) return;

    if (item.productId === 'custom') {
        item.isCustom = true;
        item.name = '';
        item.price = 0;
        return;
    }

    item.isCustom = false;
    const product = products.value.find(p => p._id === item.productId || p.id === item.productId);
    
    if (product) {
        item.name = product.name;
        item.price = product.price;
    }
};

const addItem = () => {
    form.items.push({ productId: '', name: '', price: 0, qty: 1, isCustom: false });
};

const removeItem = (index: number) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const totalEstimatedCost = computed(() => {
    return form.items.reduce((total, item) => total + (item.price * item.qty), 0);
});

const isSubmitting = ref(false);
const errorMessage = ref('');

const submitRequest = async () => {
    isSubmitting.value = true;
    errorMessage.value = '';
    
    try {
        const payloadItems = form.items.map(item => ({
            name: item.name,
            price: item.price,
            qty: item.qty
        }));

        await api.post('/requisitions', {
            justification: form.justification,
            items: payloadItems
        });
        router.push('/');
    } catch (error) {
        if (axios.isAxiosError(error) && error.response) {
            if (error.response.status === 401) {
                errorMessage.value = "Session expired. Please log out and log back in.";
            } else if (error.response.status === 422) {
                errorMessage.value = "Validation Error: Please check all fields.";
            } else {
                errorMessage.value = "Server error occurred. Could not submit.";
            }
        } else {
            errorMessage.value = "An unexpected error occurred.";
        }
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<script lang="ts">
export default {
    name: 'RequisitionCreateView'
}
</script>

<template>
    <MainLayout>
        <div class="mb-4 d-flex align-items-center gap-3">
            <button @click="router.push('/')" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left"></i>
            </button>
            <div>
                <h3 class="fw-bold text-dark mb-0">Create Request</h3>
                <p class="text-muted mb-0 small">Submit a new purchase requisition for approval.</p>
            </div>
        </div>

        <div v-if="errorMessage" class="alert alert-danger shadow-sm py-2 mb-4">
            <i class="fa-solid fa-circle-exclamation me-2"></i>{{ errorMessage }}
        </div>

        <div class="card border-0 shadow-sm p-4">
            <form @submit.prevent="submitRequest">
                <h6 class="text-uppercase text-muted small fw-bold mb-3">General Information</h6>
                <div class="row mb-4">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label class="form-label small fw-bold text-secondary">Requester Name</label>
                        <input :value="currentUser.name" type="text" class="form-control bg-light text-muted" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">Department</label>
                        <input :value="currentUser.department" type="text" class="form-control bg-light text-muted" disabled>
                    </div>
                </div>

                <div class="mb-5">
                    <label class="form-label small fw-bold text-secondary">Business Justification</label>
                    <textarea v-model="form.justification" class="form-control" rows="3" placeholder="Explain why this purchase is necessary... If requesting a custom item, provide the vendor link here." required minlength="10"></textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-uppercase text-muted small fw-bold mb-0">Line Items</h6>
                    <button type="button" @click="addItem" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-plus me-1"></i>Add Item
                    </button>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="small text-secondary">Catalog Item</th>
                                <th class="small text-secondary" style="width: 150px;">Price ($)</th>
                                <th class="small text-secondary" style="width: 120px;">Qty</th>
                                <th class="small text-secondary text-end" style="width: 120px;">Total</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in form.items" :key="index">
                                <td class="p-0">
                                    <div class="d-flex flex-column">
                                        <select v-model="item.productId" @change="handleProductSelect(index)" class="form-select border-0 rounded-0 px-3 py-2" required>
                                            <option value="" disabled>Select an item...</option>
                                            <option class="fw-bold text-primary" value="custom">+ Request Custom Item (Not in Catalog)</option>
                                            <option v-for="product in products" :key="product._id || product.id" :value="product._id || product.id">
                                                {{ product.name }} ({{ product.sku }})
                                            </option>
                                        </select>
                                        <input v-if="item.isCustom" v-model="item.name" type="text" class="form-control border-top border-0 rounded-0 px-3 py-2 bg-light text-primary" placeholder="Type custom item name..." required>
                                    </div>
                                </td>
                                <td class="p-0" :class="{ 'bg-light': !item.isCustom }">
                                    <input v-model.number="item.price" type="number" step="0.01" min="0" class="form-control border-0 rounded-0 px-3 py-2" :class="{ 'bg-transparent text-muted': !item.isCustom }" :readonly="!item.isCustom" required>
                                </td>
                                <td class="p-0">
                                    <input v-model.number="item.qty" type="number" min="1" class="form-control border-0 rounded-0 px-3 py-2" required>
                                </td>
                                <td class="text-end fw-bold px-3">
                                    ${{ (item.price * item.qty).toFixed(2) }}
                                </td>
                                <td class="text-center p-0">
                                    <button type="button" @click="removeItem(index)" class="btn btn-sm btn-link text-danger" :disabled="form.items.length === 1">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="bg-light p-4 rounded d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 border">
                    <div class="mb-3 mb-md-0 text-center text-md-start">
                        <span class="text-muted small d-block text-uppercase fw-bold">Total Estimated Cost</span>
                        <h4 class="fw-bold text-dark mb-0">${{ totalEstimatedCost.toFixed(2) }}</h4>
                    </div>
                    <button type="submit" class="btn btn-primary px-4 py-2 fw-bold" :disabled="isSubmitting">
                        <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        <i v-else class="fa-solid fa-paper-plane me-2"></i>
                        {{ isSubmitting ? 'Submitting...' : 'Submit Request' }}
                    </button>
                </div>
            </form>
        </div>
    </MainLayout>
</template>

<style scoped>
.table .form-control:focus, .table .form-select:focus {
    box-shadow: none;
    background-color: #f8f9fa;
}
</style>