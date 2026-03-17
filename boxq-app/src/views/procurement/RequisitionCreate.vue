<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
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

interface DraftItem {
    name: string;
    price: number;
    qty: number;
}

interface CostCenter {
    department: string;
    percentage: number;
}

const router = useRouter();
const route = useRoute();
const draftId = route.query.id as string | undefined;
const cloneId = route.query.clone_id as string | undefined;

const currentUser = ref({
    name: 'Loading...',
    department: 'Loading...'
});

const products = ref<Product[]>([]);
const attachmentFile = ref<File | null>(null);

const availableDepartments = ['Engineering', 'HR', 'Finance', 'IT', 'Marketing', 'Sales', 'Operations'];

const form = reactive({
    justification: '',
    currency: 'USD',
    exchange_rate: 15500,
    has_tax: true,
    items: [
        { productId: '', name: '', price: 0, qty: 1, isCustom: false }
    ],
    cost_centers: [] as CostCenter[]
});

const budget = ref({
    limit: 0,
    spent: 0,
    remaining: 0
});

onMounted(async () => {
    const userData = localStorage.getItem('user');
    if (userData) {
        const user = JSON.parse(userData);
        currentUser.value.name = user.name || 'Unknown';
        currentUser.value.department = user.department || 'Unknown';
        form.cost_centers.push({ 
            department: user.department || 'Engineering', 
            percentage: 100 
        });
    }

    try {
        const [productResponse, budgetResponse] = await Promise.all([
            api.get('/products'),
            api.get('/budget/current').catch(() => ({ data: { limit: 0, spent: 0, remaining: 0 } }))
        ]);
        
        products.value = productResponse.data;
        budget.value = budgetResponse.data;

        const targetId = draftId || cloneId;

        if (targetId) {
            const targetResponse = await api.get(`/requisitions/${targetId}`);
            const targetData = targetResponse.data;
            
            form.justification = targetData.justification || '';
            form.currency = targetData.currency || 'USD';
            form.exchange_rate = targetData.exchange_rate || 15500;
            form.has_tax = targetData.has_tax !== undefined ? targetData.has_tax : true;
            
            if (targetData.items && targetData.items.length > 0) {
                form.items = targetData.items.map((item: DraftItem) => {
                    const matchedProduct = products.value.find(p => p.name === item.name);
                    return {
                        productId: matchedProduct ? (matchedProduct._id || matchedProduct.id) : 'custom',
                        name: item.name || '',
                        price: item.price || 0,
                        qty: item.qty || 1,
                        isCustom: !matchedProduct
                    };
                });
            }

            if (targetData.cost_centers && targetData.cost_centers.length > 0) {
                form.cost_centers = targetData.cost_centers.map((cc: CostCenter) => ({
                    department: cc.department || 'Engineering',
                    percentage: Number(cc.percentage) || 0
                }));
            }
        }
    } catch (error) {
        console.error("Error loading initial data", error);
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

const handleFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        attachmentFile.value = target.files[0] || null;
    } else {
        attachmentFile.value = null;
    }
};

const addItem = () => form.items.push({ productId: '', name: '', price: 0, qty: 1, isCustom: false });
const removeItem = (index: number) => { if (form.items.length > 1) form.items.splice(index, 1); };

const addCostCenter = () => form.cost_centers.push({ department: 'Engineering', percentage: 0 });
const removeCostCenter = (index: number) => { if (form.cost_centers.length > 1) form.cost_centers.splice(index, 1); };

const totalPercentage = computed(() => {
    return form.cost_centers.reduce((sum, cc) => sum + (Number(cc.percentage) || 0), 0);
});

const subtotal = computed(() => {
    return form.items.reduce((total, item) => total + (Number(item.price) * Number(item.qty)), 0);
});

const taxAmount = computed(() => {
    return form.has_tax ? (subtotal.value * 0.11) : 0;
});

const grandTotal = computed(() => {
    return subtotal.value + taxAmount.value;
});

const grandTotalUSD = computed(() => {
    return form.currency === 'IDR' ? (grandTotal.value / form.exchange_rate) : grandTotal.value;
});

const isSoftWarning = computed(() => {
    if (budget.value.limit === 0) return false;
    return (budget.value.spent + grandTotalUSD.value) > budget.value.limit && grandTotalUSD.value <= budget.value.limit;
});

const isHardBlock = computed(() => {
    if (budget.value.limit === 0) return false;
    return grandTotalUSD.value > budget.value.limit;
});

const isSubmitting = ref(false);
const errorMessage = ref('');

const submitRequest = async (targetStatus: 'Pending' | 'Draft') => {
    errorMessage.value = '';

    if (targetStatus === 'Pending') {
        if (!form.justification || form.justification.length < 10) {
            errorMessage.value = "Validation Error: Justification must be at least 10 characters.";
            return;
        }
        
        const hasInvalidItems = form.items.some(i => !i.name || i.price <= 0 || i.qty < 1);
        if (hasInvalidItems) {
            errorMessage.value = "Validation Error: All line items must have a valid name, price, and quantity.";
            return;
        }

        if (totalPercentage.value !== 100) {
            errorMessage.value = "Validation Error: Cost center allocations must equal exactly 100%.";
            return;
        }

        if (isHardBlock.value) {
            errorMessage.value = "Hard Block: This request exceeds your entire monthly department budget.";
            return;
        }
    }

    isSubmitting.value = true;
    
    try {
        const formData = new FormData();
        formData.append('justification', form.justification);
        formData.append('status', targetStatus);
        formData.append('currency', form.currency);
        formData.append('exchange_rate', form.exchange_rate.toString());
        formData.append('has_tax', form.has_tax ? '1' : '0');
        
        form.items.forEach((item, index) => {
            formData.append(`items[${index}][name]`, item.name || '');
            formData.append(`items[${index}][price]`, item.price.toString());
            formData.append(`items[${index}][qty]`, item.qty.toString());
        });

        form.cost_centers.forEach((cc, index) => {
            formData.append(`cost_centers[${index}][department]`, cc.department);
            formData.append(`cost_centers[${index}][percentage]`, cc.percentage.toString());
        });

        if (attachmentFile.value) {
            formData.append('attachment', attachmentFile.value);
        }

        if (draftId) {
            formData.append('_method', 'PUT');
            await api.post(`/requisitions/${draftId}`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
        } else {
            await api.post('/requisitions', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
        }
        
        router.push('/');
    } catch (error) {
        if (axios.isAxiosError(error) && error.response) {
            if (error.response.data?.error) {
                errorMessage.value = error.response.data.error;
            } else if (error.response.status === 401) {
                errorMessage.value = "Session expired. Please log out and log back in.";
            } else if (error.response.status === 422) {
                errorMessage.value = "Backend Validation Error: Please check all fields.";
            } else {
                errorMessage.value = "Server error occurred. Could not save.";
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
                <h3 class="fw-bold text-dark mb-0">{{ draftId ? 'Edit Draft' : (cloneId ? 'Clone Request' : 'Create Request') }}</h3>
                <p class="text-muted mb-0 small">{{ draftId ? 'Finish your saved draft.' : 'Submit a new purchase requisition for approval.' }}</p>
            </div>
        </div>

        <div v-if="budget.limit > 0" class="card border-0 shadow-sm mb-4 p-4 rounded-3">
            <h6 class="fw-bold text-secondary mb-3 text-uppercase small">Department Budget Status (USD)</h6>
            <div class="d-flex justify-content-between mb-1 small fw-bold">
                <span>Spent: ${{ budget.spent.toLocaleString() }}</span>
                <span>Limit: ${{ budget.limit.toLocaleString() }}</span>
            </div>
            <div class="progress" style="height: 10px;">
                <div class="progress-bar bg-dark" role="progressbar" :style="`width: ${(budget.spent / budget.limit) * 100}%`"></div>
            </div>
        </div>

        <div v-if="errorMessage" class="alert alert-danger shadow-sm py-2 mb-4">
            <i class="fa-solid fa-circle-exclamation me-2"></i> {{ errorMessage }}
        </div>

        <div v-if="isSoftWarning" class="alert alert-warning fw-bold border-0 shadow-sm mb-4 text-dark">
            <i class="fa-solid fa-circle-info me-2"></i> Soft Warn: This purchase will push your department over its monthly budget. It will be flagged for review.
        </div>

        <div class="card border-0 shadow-sm p-4">
            <form>
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

                <div class="mb-4">
                    <label class="form-label small fw-bold text-secondary">Business Justification</label>
                    <textarea v-model="form.justification" class="form-control" rows="3" placeholder="Explain why this purchase is necessary..."></textarea>
                </div>

                <div class="mb-5">
                    <label class="form-label small fw-bold text-secondary">Supporting Documentation</label>
                    <input type="file" @change="handleFileUpload" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
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
                                <th class="small text-secondary" style="width: 150px;">Price</th>
                                <th class="small text-secondary" style="width: 120px;">Qty</th>
                                <th class="small text-secondary text-end" style="width: 120px;">Total</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in form.items" :key="index">
                                <td class="p-0">
                                    <div class="d-flex flex-column">
                                        <select v-model="item.productId" @change="handleProductSelect(index)" class="form-select border-0 rounded-0 px-3 py-2">
                                            <option value="" disabled>Select an item...</option>
                                            <option class="fw-bold text-primary" value="custom">+ Request Custom Item (Not in Catalog)</option>
                                            <option v-for="product in products" :key="product._id || product.id" :value="product._id || product.id">
                                                {{ product.name }} ({{ product.sku }})
                                            </option>
                                        </select>
                                        <input v-if="item.isCustom" v-model="item.name" type="text" class="form-control border-top border-0 rounded-0 px-3 py-2 bg-light text-primary" placeholder="Type custom item name...">
                                    </div>
                                </td>
                                <td class="p-0" :class="{ 'bg-light': !item.isCustom }">
                                    <input v-model.number="item.price" type="number" step="0.01" min="0" class="form-control border-0 rounded-0 px-3 py-2" :class="{ 'bg-transparent text-muted': !item.isCustom }" :readonly="!item.isCustom">
                                </td>
                                <td class="p-0">
                                    <input v-model.number="item.qty" type="number" min="1" class="form-control border-0 rounded-0 px-3 py-2">
                                </td>
                                <td class="text-end fw-bold px-3">
                                    {{ form.currency === 'USD' ? '$' : 'Rp' }}{{ (item.price * item.qty).toLocaleString() }}
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

                <div class="row mb-4">
                    <div class="col-md-7">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-uppercase text-muted small fw-bold mb-0">Cost Center Allocation</h6>
                            <button type="button" @click="addCostCenter" class="btn btn-sm btn-outline-secondary">
                                <i class="fa-solid fa-plus me-1"></i>Add Split
                            </button>
                        </div>
                        <div v-for="(cc, index) in form.cost_centers" :key="index" class="d-flex gap-2 mb-2 align-items-center">
                            <select v-model="cc.department" class="form-select w-50">
                                <option v-for="dept in availableDepartments" :key="dept" :value="dept">{{ dept }}</option>
                            </select>
                            <div class="input-group w-50">
                                <input v-model.number="cc.percentage" type="number" min="1" max="100" class="form-control text-end">
                                <span class="input-group-text">%</span>
                            </div>
                            <button type="button" @click="removeCostCenter(index)" class="btn btn-link text-danger p-0 ms-1" :disabled="form.cost_centers.length === 1">
                                <i class="fa-solid fa-circle-minus"></i>
                            </button>
                        </div>
                        <div class="small mt-2" :class="totalPercentage === 100 ? 'text-success' : 'text-danger fw-bold'">
                            Total Allocated: {{ totalPercentage }}% (Must equal 100%)
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="bg-light p-4 rounded border h-100 d-flex flex-column justify-content-center">
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                                <span class="text-muted small fw-bold text-uppercase">Currency</span>
                                <div class="d-flex gap-2 align-items-center">
                                    <select v-model="form.currency" class="form-select form-select-sm w-auto fw-bold">
                                        <option value="USD">USD</option>
                                        <option value="IDR">IDR</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div v-if="form.currency === 'USD'" class="mb-3 d-flex justify-content-between align-items-center">
                                <span class="text-muted small">IDR Exchange Rate</span>
                                <input v-model.number="form.exchange_rate" type="number" class="form-control form-control-sm w-50 text-end">
                            </div>

                            <div class="mb-2 d-flex justify-content-between align-items-center">
                                <span class="text-muted small fw-bold">Subtotal:</span>
                                <span class="fw-bold text-dark">{{ form.currency === 'USD' ? '$' : 'Rp' }}{{ subtotal.toLocaleString(undefined, {minimumFractionDigits: 2}) }}</span>
                            </div>

                            <div class="mb-3 d-flex justify-content-between align-items-center border-bottom pb-3">
                                <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
                                    <input class="form-check-input mt-0" type="checkbox" v-model="form.has_tax" id="taxToggle" style="cursor: pointer;">
                                    <label class="form-check-label text-muted small fw-bold m-0" for="taxToggle" style="cursor: pointer;">Apply VAT / PPN (11%)</label>
                                </div>
                                <span class="fw-bold text-dark">{{ form.currency === 'USD' ? '$' : 'Rp' }}{{ taxAmount.toLocaleString(undefined, {minimumFractionDigits: 2}) }}</span>
                            </div>

                            <div class="text-end mt-auto">
                                <span class="text-muted small d-block text-uppercase fw-bold mb-1">Grand Total</span>
                                <h3 class="fw-bold text-dark mb-0">
                                    {{ form.currency === 'USD' ? '$' : 'Rp' }}{{ grandTotal.toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                                </h3>
                                <div v-if="form.currency === 'USD'" class="text-muted small mt-1">
                                    ≈ Rp{{ (grandTotal * form.exchange_rate).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <button type="button" @click="submitRequest('Draft')" class="btn btn-outline-secondary px-4 py-2 fw-bold" :disabled="isSubmitting">
                        <i class="fa-solid fa-floppy-disk me-2"></i> Save Draft
                    </button>
                    <button type="button" @click="submitRequest('Pending')" class="btn btn-primary px-4 py-2 fw-bold" :disabled="isSubmitting || isHardBlock || form.items.length === 0">
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