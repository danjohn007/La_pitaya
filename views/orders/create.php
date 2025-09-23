<?php $title = 'Nuevo Pedido'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-plus-circle"></i> Nuevo Pedido</h1>
    <a href="<?= BASE_URL ?>/orders" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver a Pedidos
    </a>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/orders/create" id="orderForm">
    <div class="row">
        <!-- Order Details -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle"></i> Detalles del Pedido
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Category Filter Buttons -->
                    <div class="mb-3" id="categoryFilterContainer">
                        <div class="btn-group" role="group" aria-label="Filtrar por categoría">
                            <button type="button" class="btn btn-outline-primary btn-category active" data-category="all">Todas</button>
                            <?php 
                            $categories = array();
                            foreach ($dishes as $dish) {
                                if (!in_array($dish['category'], $categories)) {
                                    $categories[] = $dish['category'];
                                }
                            }
                            foreach ($categories as $cat): ?>
                                <button type="button" class="btn btn-outline-primary btn-category" data-category="<?= strtolower(htmlspecialchars($cat)) ?>">
                                    <?= htmlspecialchars($cat) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="table_id" class="form-label">Mesa *</label>
                        <select class="form-select <?= isset($errors['table_id']) ? 'is-invalid' : '' ?>" 
                                id="table_id" 
                                name="table_id" 
                                required>
                            <option value="">Seleccionar mesa...</option>
                            <?php foreach ($tables as $table): ?>
                                <option value="<?= $table['id'] ?>" 
                                        <?= (($old['table_id'] ?? '') == $table['id']) ? 'selected' : '' ?>>
                                    Mesa <?= $table['number'] ?> 
                                    (Cap: <?= $table['capacity'] ?>) 
                                    - <?= htmlspecialchars($table['zone'] ?? 'Sin zona') ?>
                                    - <?= ucfirst($table['status']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['table_id'])): ?>
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($errors['table_id']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($user['role'] === ROLE_ADMIN || $user['role'] === ROLE_CASHIER): ?>
                    <div class="mb-3">
                        <label for="waiter_id" class="form-label">Mesero *</label>
                        <select class="form-select <?= isset($errors['waiter_id']) ? 'is-invalid' : '' ?>" 
                                id="waiter_id" 
                                name="waiter_id" 
                                required>
                            <option value="">Seleccionar mesero...</option>
                            <?php foreach ($waiters as $waiter): ?>
                                <option value="<?= $waiter['id'] ?>" 
                                        <?= (($old['waiter_id'] ?? '') == $waiter['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($waiter['name']) ?> 
                                    (<?= htmlspecialchars($waiter['employee_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['waiter_id'])): ?>
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($errors['waiter_id']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Customer Search Section -->
                    <div class="mb-3">
                        <label class="form-label">Cliente (Opcional)</label>
                        <div class="input-group mb-2">
                            <input type="text" 
                                   class="form-control" 
                                   id="customer_search" 
                                   placeholder="Buscar por nombre o teléfono..."
                                   autocomplete="off">
                            <button type="button" 
                                    class="btn btn-outline-secondary" 
                                    id="clear_customer">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                        
                        <!-- Customer Results -->
                        <div id="customer_results" class="list-group mb-2" style="display: none;"></div>
                        
                        <!-- Selected Customer Display -->
                        <div id="selected_customer" class="alert alert-info" style="display: none;">
                            <strong>Cliente seleccionado:</strong> 
                            <span id="customer_display"></span>
                            <input type="hidden" id="customer_id" name="customer_id" value="">
                        </div>
                        
                        <!-- New Customer Form -->
                        <div class="row" id="new_customer_form" style="display: none;">
                            <div class="col-md-6">
                                <input type="text" 
                                       class="form-control form-control-sm" 
                                       id="new_customer_name" 
                                       name="new_customer_name" 
                                       placeholder="Nombre completo">
                            </div>
                            <div class="col-md-6">
                                <input type="tel" 
                                       class="form-control form-control-sm" 
                                       id="new_customer_phone" 
                                       name="new_customer_phone" 
                                       placeholder="Teléfono">
                            </div>
                        </div>
                        
                        <div class="form-text">
                            Busque un cliente existente o deje vacío para pedido sin cliente asignado
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notas del Pedido</label>
                        <textarea class="form-control" 
                                  id="notes" 
                                  name="notes" 
                                  rows="3" 
                                  placeholder="Instrucciones especiales..."><?= htmlspecialchars($old['notes'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="card-title">Total del Pedido</h6>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="card-title mb-0">Total del Pedido</h6>
                                <div class="d-flex gap-2">
                                    <a href="<?= BASE_URL ?>/orders" class="btn btn-secondary btn-sm" id="cancelOrderBtn">
                                        <i class="bi bi-x-circle"></i> Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-sm" id="confirmOrderBtn">
                                        <i class="bi bi-check-circle"></i> Confirmar
                                    </button>
                                </div>
                            </div>
                            <h3 class="text-primary mb-0" id="orderTotal">$0.00</h3>
                            <div class="mt-3">
                                <h6 class="mb-2">Platillos agregados:</h6>
                                <ul id="addedDishesList" class="list-group"></ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Menu Items -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-cup-hot"></i> Seleccionar Platillos
                    </h5>
                    <br>
                    <!-- Category Filter Buttons (dentro de la tarjeta) -->
                    <div class="mb-3" id="categoryFilterContainer">
                        <div class="d-flex flex-wrap gap-2" style="margin-bottom: 0.5rem;">
                            <button type="button" class="btn btn-outline-primary btn-category active" data-category="all">Todas</button>
                            <?php 
                            $categories = array();
                            foreach ($dishes as $dish) {
                                if (!in_array($dish['category'], $categories)) {
                                    $categories[] = $dish['category'];
                                }
                            }
                            foreach ($categories as $cat): ?>
                                <button type="button" class="btn btn-outline-primary btn-category" data-category="<?= strtolower(htmlspecialchars($cat)) ?>" style="margin-bottom: 4px;">
                                    <?= htmlspecialchars($cat) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (isset($errors['items'])): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($errors['items']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Product Search -->
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="dish_search" 
                                   placeholder="Buscar platillo por nombre o categoría..."
                                   autocomplete="off">
                            <button type="button" 
                                    class="btn btn-outline-secondary" 
                                    id="clear_dish_search">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            Escriba para filtrar los platillos disponibles en tiempo real
                        </div>
                    </div>
                    
                    <!-- Dishes Container -->
                    <div id="dishes_container">
                    <?php 
                    $currentCategory = '';
                    foreach ($dishes as $dish): 
                        if ($dish['category'] !== $currentCategory):
                            if ($currentCategory !== ''): ?>
                                </div>
                            <?php endif; ?>
                            <h6 class="text-muted mb-3 mt-4 category-header" 
                                data-category="<?= htmlspecialchars($dish['category']) ?>">
                                <?= htmlspecialchars($dish['category']) ?>
                            </h6>
                            <div class="row category-dishes" data-category="<?= htmlspecialchars($dish['category']) ?>">
                        <?php 
                            $currentCategory = $dish['category'];
                        endif; 
                    ?>
                        <div class="col-md-6 mb-3 dish-item" 
                             data-dish-name="<?= strtolower(htmlspecialchars($dish['name'])) ?>"
                             data-dish-category="<?= strtolower(htmlspecialchars($dish['category'])) ?>"
                             data-dish-description="<?= strtolower(htmlspecialchars($dish['description'] ?? '')) ?>">
                            <div class="card dish-card" data-dish-id="<?= $dish['id'] ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0"><?= htmlspecialchars($dish['name']) ?></h6>
                                        <strong class="text-success fs-5 fw-bold price-highlight-order">
                                            $<?= number_format($dish['price'], 2) ?>
                                        </strong>
                                    </div>
                                    <?php if ($dish['description']): ?>
                                        <p class="text-muted small mb-2"><?= htmlspecialchars($dish['description']) ?></p>
                                    <?php endif; ?>
                                    <div class="input-group input-group-sm">
                                        <button type="button" class="btn btn-outline-secondary btn-minus" data-dish-id="<?= $dish['id'] ?>">-</button>
                                        <input type="number" 
                                               class="form-control text-center dish-quantity" 
                                               name="items[<?= $dish['id'] ?>][dish_id]" 
                                               value="<?= $dish['id'] ?>"
                                               style="display: none;">
                                        <input type="number" 
                                               class="form-control text-center dish-quantity" 
                                               name="items[<?= $dish['id'] ?>][quantity]" 
                                               value="0" 
                                               min="0" 
                                               max="99"
                                               data-price="<?= $dish['price'] ?>"
                                               data-dish-id="<?= $dish['id'] ?>">
                                        <button type="button" class="btn btn-outline-secondary btn-plus" data-dish-id="<?= $dish['id'] ?>">+</button>
                                    </div>
                                    <input type="text" 
                                           class="form-control form-control-sm mt-2" 
                                           name="items[<?= $dish['id'] ?>][notes]" 
                                           placeholder="Notas especiales..."
                                           style="display: none;">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($currentCategory !== ''): ?>
                        </div>
                    <?php endif; ?>
                    </div>
                    
                    <!-- No Results Message -->
                    <div id="no_results" class="text-center py-4" style="display: none;">
                        <i class="bi bi-search text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">No se encontraron platillos que coincidan con su búsqueda</p>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="clear_search_btn">
                            <i class="bi bi-x-circle"></i> Limpiar búsqueda
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex justify-content-end gap-2">
                <a href="<?= BASE_URL ?>/orders" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Crear Pedido
                </button>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('orderForm');
    const totalElement = document.getElementById('orderTotal');
    
    // Customer search functionality
    const customerSearch = document.getElementById('customer_search');
    const customerResults = document.getElementById('customer_results');
    const selectedCustomer = document.getElementById('selected_customer');
    const customerDisplay = document.getElementById('customer_display');
    const customerIdInput = document.getElementById('customer_id');
    const clearCustomerBtn = document.getElementById('clear_customer');
    const newCustomerForm = document.getElementById('new_customer_form');
    
    // Dish search functionality
    // Categoría de platillos
    const categoryButtons = document.querySelectorAll('.btn-category');

    categoryButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            categoryButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            filterByCategory(btn.dataset.category);
        });
    });

    function filterByCategory(category) {
        const allDishes = document.querySelectorAll('.dish-item');
        const allCategoryContainers = document.querySelectorAll('.category-dishes');
        const allCategoryHeaders = document.querySelectorAll('.category-header');
        if (category === 'all') {
            allDishes.forEach(d => d.style.display = 'block');
            allCategoryContainers.forEach(c => c.style.display = 'flex');
            allCategoryHeaders.forEach(h => h.style.display = 'block');
            return;
        }
        allDishes.forEach(dish => {
            if (dish.dataset.dishCategory === category) {
                dish.style.display = 'block';
            } else {
                dish.style.display = 'none';
            }
        });
        allCategoryContainers.forEach(container => {
            if (container.dataset.category.toLowerCase() === category) {
                container.style.display = 'flex';
            } else {
                container.style.display = 'none';
            }
        });
        allCategoryHeaders.forEach(header => {
            if (header.dataset.category.toLowerCase() === category) {
                header.style.display = 'block';
            } else {
                header.style.display = 'none';
            }
        });
    }
    const dishSearch = document.getElementById('dish_search');
    const clearDishSearchBtn = document.getElementById('clear_dish_search');
    const dishesContainer = document.getElementById('dishes_container');
    const noResults = document.getElementById('no_results');
    const clearSearchBtn = document.getElementById('clear_search_btn');
    
    let searchTimeout;
    
    // Customer search input handler
    customerSearch.addEventListener('input', function() {
        const query = this.value.trim();
        
        if (query.length < 2) {
            customerResults.style.display = 'none';
            return;
        }
        
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchCustomers(query);
        }, 300);
    });
    
    // Clear customer selection
    clearCustomerBtn.addEventListener('click', function() {
        clearCustomerSelection();
    });
    
    // Dish search input handler
    dishSearch.addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();
        filterDishes(query);
    });
    
    // Clear dish search
    clearDishSearchBtn.addEventListener('click', function() {
        dishSearch.value = '';
        filterDishes('');
    });
    
    // Clear search button in no results
    clearSearchBtn.addEventListener('click', function() {
        dishSearch.value = '';
        filterDishes('');
    });
    
    // Filter dishes based on search query
    function filterDishes(query) {
        const dishes = document.querySelectorAll('.dish-item');
        const categories = document.querySelectorAll('.category-header');
        const categoryContainers = document.querySelectorAll('.category-dishes');
        let hasVisibleDishes = false;
        
        if (query === '') {
            // Show all dishes and categories
            dishes.forEach(dish => dish.style.display = 'block');
            categories.forEach(cat => cat.style.display = 'block');
            categoryContainers.forEach(container => container.style.display = 'flex');
            dishesContainer.style.display = 'block';
            noResults.style.display = 'none';
            return;
        }
        
        // Filter dishes by name, category, or description
        dishes.forEach(dish => {
            const name = dish.dataset.dishName || '';
            const category = dish.dataset.dishCategory || '';
            const description = dish.dataset.dishDescription || '';
            
            const matches = name.includes(query) || 
                          category.includes(query) || 
                          description.includes(query);
            
            if (matches) {
                dish.style.display = 'block';
                hasVisibleDishes = true;
            } else {
                dish.style.display = 'none';
            }
        });
        
        // Show/hide category headers based on visible dishes
        categoryContainers.forEach(container => {
            const categoryName = container.dataset.category;
            const visibleDishesInCategory = container.querySelectorAll('.dish-item[style*="block"]').length;
            const categoryHeader = document.querySelector(`.category-header[data-category="${categoryName}"]`);
            
            if (visibleDishesInCategory > 0) {
                container.style.display = 'flex';
                if (categoryHeader) categoryHeader.style.display = 'block';
            } else {
                container.style.display = 'none';
                if (categoryHeader) categoryHeader.style.display = 'none';
            }
        });
        
        // Show/hide no results message
        if (hasVisibleDishes) {
            dishesContainer.style.display = 'block';
            noResults.style.display = 'none';
        } else {
            dishesContainer.style.display = 'none';
            noResults.style.display = 'block';
        }
    }
    
    // Search customers via AJAX
    function searchCustomers(query) {
        fetch('<?= BASE_URL ?>/orders/searchCustomers', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ query: query })
        })
        .then(response => response.json())
        .then(data => {
            displayCustomerResults(data.customers || []);
        })
        .catch(error => {
            console.error('Error searching customers:', error);
            customerResults.innerHTML = '<div class="list-group-item text-danger">Error al buscar clientes</div>';
            customerResults.style.display = 'block';
        });
    }
    
    // Display customer search results
    function displayCustomerResults(customers) {
        customerResults.innerHTML = '';
        
        if (customers.length === 0) {
            customerResults.innerHTML = `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>No se encontraron clientes</span>
                        <button type="button" class="btn btn-sm btn-primary" onclick="showNewCustomerForm()">
                            <i class="bi bi-plus"></i> Crear Nuevo
                        </button>
                    </div>
                </div>
            `;
        } else {
            customers.forEach(customer => {
                const item = document.createElement('div');
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${customer.name}</strong><br>
                            <small class="text-muted">${customer.phone} | Visitas: ${customer.total_visits} | Gastado: $${parseFloat(customer.total_spent).toFixed(2)}</small>
                        </div>
                    </div>
                `;
                item.addEventListener('click', () => selectCustomer(customer));
                customerResults.appendChild(item);
            });
        }
        
        customerResults.style.display = 'block';
    }
    
    // Select a customer
    function selectCustomer(customer) {
        customerIdInput.value = customer.id;
        customerDisplay.textContent = `${customer.name} (${customer.phone})`;
        selectedCustomer.style.display = 'block';
        customerResults.style.display = 'none';
        newCustomerForm.style.display = 'none';
        customerSearch.value = customer.name;
    }
    
    // Clear customer selection
    function clearCustomerSelection() {
        customerIdInput.value = '';
        selectedCustomer.style.display = 'none';
        customerResults.style.display = 'none';
        newCustomerForm.style.display = 'none';
        customerSearch.value = '';
    }
    
    // Show new customer form
    window.showNewCustomerForm = function() {
        newCustomerForm.style.display = 'block';
        customerResults.style.display = 'none';
        document.getElementById('new_customer_name').value = customerSearch.value;
    }
    
    // Hide customer results when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#customer_search') && !e.target.closest('#customer_results')) {
            customerResults.style.display = 'none';
        }
    });
    
    // Handle quantity buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-plus') || e.target.classList.contains('btn-minus')) {
            const dishId = e.target.dataset.dishId;
            const quantityInput = document.querySelector(`input[name="items[${dishId}][quantity]"]`);
            const notesInput = document.querySelector(`input[name="items[${dishId}][notes]"]`);
            const dishCard = document.querySelector(`[data-dish-id="${dishId}"]`);
            
            let currentQuantity = parseInt(quantityInput.value) || 0;
            
            if (e.target.classList.contains('btn-plus')) {
                currentQuantity++;
            } else if (e.target.classList.contains('btn-minus') && currentQuantity > 0) {
                currentQuantity--;
            }
            
            quantityInput.value = currentQuantity;
            
            // Show/hide notes input
            if (currentQuantity > 0) {
                notesInput.style.display = 'block';
                dishCard.classList.add('border-primary');
            } else {
                notesInput.style.display = 'none';
                dishCard.classList.remove('border-primary');
                notesInput.value = '';
            }
            
            updateTotal();
        }
    });
    
    // Handle quantity input changes
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('dish-quantity')) {
            const dishId = e.target.dataset.dishId;
            const notesInput = document.querySelector(`input[name="items[${dishId}][notes]"]`);
            const dishCard = document.querySelector(`[data-dish-id="${dishId}"]`);
            const quantity = parseInt(e.target.value) || 0;
            
            if (quantity > 0) {
                notesInput.style.display = 'block';
                dishCard.classList.add('border-primary');
            } else {
                notesInput.style.display = 'none';
                dishCard.classList.remove('border-primary');
                notesInput.value = '';
            }
            
            updateTotal();
        }
    });
    
    function updateTotal() {
        let total = 0;
        document.querySelectorAll('.dish-quantity[name*="[quantity]"]').forEach(function(input) {
            const quantity = parseInt(input.value) || 0;
            const price = parseFloat(input.dataset.price) || 0;
            total += quantity * price;
        });
        
        totalElement.textContent = '$' + total.toFixed(2);
    }
    // Inicializar lista al cargar
    updateAddedDishesList();
});
</script>

<style>
.dish-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.dish-card:hover {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.dish-card.border-primary {
    border-color: #0d6efd !important;
    background-color: #f8f9ff;
}

.btn-minus, .btn-plus {
    width: 35px;
}
<<<<<<< HEAD

.btn-category {
    min-width: 110px;
    white-space: normal;
    word-break: break-word;
}
</style>
=======
</style>
>>>>>>> b6c28b5881afc2d2ca876a6c955c44db6d4aa183
