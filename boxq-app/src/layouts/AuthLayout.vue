<template>
    <div class="d-flex min-vh-100 align-items-center justify-content-center bg-light">
        <slot></slot>
    </div>
</template>

<style>
.input-group .form-control:focus {
    border-color: #dee2e6;
    box-shadow: none;
    outline: 0;
}

.input-group:focus-within .input-group-text,
.input-group:focus-within .form-control {
    border-color: #adb5bd !important;
}
</style>