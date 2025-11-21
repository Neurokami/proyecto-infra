<div class="product-card">
    <div class="product-main">
        <div class="product-title">
            {{ $p->nombre }}
        </div>
        <div class="product-description">
            {{ $p->descripcion }}
        </div>
    </div>
    <div class="product-price">
        {{ "$" . number_format($p->precio, 2, ",", ".") }}
    </div>
</div>