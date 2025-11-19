/* Frontend logic for Marketplace Vendedores dashboard */
(() => {
  const API_BASE = "/api";

  const authSection = document.getElementById("auth-section");
  const registerSection = document.getElementById("register-section");
  const dashboardSection = document.getElementById("dashboard-section");

  const loginForm = document.getElementById("login-form");
  const registerForm = document.getElementById("register-form");
  const productForm = document.getElementById("product-form");

  const loginDocumentoInput = document.getElementById("login-documento");
  const vendorNameEl = document.getElementById("vendor-name");
  const logoutBtn = document.getElementById("logout-btn");

  const authMessages = document.getElementById("auth-messages");
  const registerMessages = document.getElementById("register-messages");
  const productMessages = document.getElementById("product-messages");

  const productsTableBody = document.getElementById("products-table-body");
  const salesTableBody = document.getElementById("sales-table-body");
  const rowEmptyTemplate = document.getElementById("row-empty-template");

  const showRegisterBtn = document.getElementById("show-register");
  const showLoginBtn = document.getElementById("show-login");
  const refreshProductsBtn = document.getElementById("refresh-products");
  const refreshSalesBtn = document.getElementById("refresh-sales");

  const STORAGE_KEY = "marketplace_vendor";

  function saveVendorSession(vendor) {
    if (!vendor) return;
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(vendor));
  }

  function getVendorSession() {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    try {
      return JSON.parse(raw);
    } catch {
      sessionStorage.removeItem(STORAGE_KEY);
      return null;
    }
  }

  function clearVendorSession() {
    sessionStorage.removeItem(STORAGE_KEY);
  }

  function setSection(section) {
    authSection.classList.add("hidden");
    registerSection.classList.add("hidden");
    dashboardSection.classList.add("hidden");

    if (section === "auth") {
      authSection.classList.remove("hidden");
      logoutBtn.classList.add("hidden");
      loginDocumentoInput.focus();
    } else if (section === "register") {
      registerSection.classList.remove("hidden");
      logoutBtn.classList.add("hidden");
    } else if (section === "dashboard") {
      dashboardSection.classList.remove("hidden");
      logoutBtn.classList.remove("hidden");
    }
  }

  function clearMessages() {
    authMessages.innerHTML = "";
    registerMessages.innerHTML = "";
    productMessages.innerHTML = "";
  }

  function renderMessage(container, type, text) {
    if (!container) return;
    container.innerHTML = "";
    if (!text) return;
    const div = document.createElement("div");
    div.className = `messages__item messages__item--${type}`;
    div.textContent = text;
    container.appendChild(div);
  }

  async function apiRequest(path, options = {}) {
    const opts = {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
        ...(options.headers || {}),
      },
      ...options,
    };

    const response = await fetch(`${API_BASE}${path}`, opts);
    let payload = null;
    try {
      payload = await response.json();
    } catch {
      payload = null;
    }

    if (!response.ok) {
      const message = payload?.message || payload?.error || "Error en la petición";
      const error = new Error(message);
      error.status = response.status;
      throw error;
    }

    return payload;
  }

  async function handleLogin(event) {
    event.preventDefault();
    clearMessages();

    const documento = loginDocumentoInput.value.trim();
    if (!documento) {
      renderMessage(authMessages, "error", "Debes ingresar un documento.");
      return;
    }

    try {
      const payload = await apiRequest("/auth/login.php", {
        method: "POST",
        body: JSON.stringify({ documento }),
      });

      if (!payload || !payload.vendedor) {
        renderMessage(authMessages, "error", "Respuesta inesperada del servidor.");
        return;
      }

      saveVendorSession(payload.vendedor);
      vendorNameEl.textContent = payload.vendedor.nombre || payload.vendedor.documento;
      setSection("dashboard");
      await Promise.all([loadProducts(), loadSales()]);
      renderMessage(productMessages, "success", "Sesión iniciada correctamente.");
    } catch (error) {
      if (error.status === 404) {
        renderMessage(
          authMessages,
          "error",
          "No encontramos un vendedor con ese documento. Regístrate para continuar."
        );
        setSection("register");
        const regDocumento = document.getElementById("reg-documento");
        if (regDocumento) regDocumento.value = documento;
        return;
      }

      renderMessage(authMessages, "error", error.message || "No se pudo iniciar sesión.");
    }
  }

  async function handleRegister(event) {
    event.preventDefault();
    clearMessages();

    const documento = document.getElementById("reg-documento").value.trim();
    const nombre = document.getElementById("reg-nombre").value.trim();
    const telefono = document.getElementById("reg-telefono").value.trim();
    const email = document.getElementById("reg-email").value.trim();

    if (!documento || !nombre) {
      renderMessage(registerMessages, "error", "Documento y nombre son obligatorios.");
      return;
    }

    try {
      const payload = await apiRequest("/auth/register.php", {
        method: "POST",
        body: JSON.stringify({ documento, nombre, telefono, email }),
      });

      if (!payload || !payload.vendedor) {
        renderMessage(registerMessages, "error", "Respuesta inesperada del servidor.");
        return;
      }

      saveVendorSession(payload.vendedor);
      vendorNameEl.textContent = payload.vendedor.nombre || payload.vendedor.documento;
      setSection("dashboard");
      await Promise.all([loadProducts(), loadSales()]);
      renderMessage(
        registerMessages,
        "success",
        "Registro completado. Ya puedes gestionar tus productos."
      );
    } catch (error) {
      renderMessage(
        registerMessages,
        "error",
        error.message || "No se pudo completar el registro. Intenta nuevamente."
      );
    }
  }

  async function handleCreateProduct(event) {
    event.preventDefault();
    clearMessages();

    const vendor = getVendorSession();
    if (!vendor) {
      setSection("auth");
      renderMessage(authMessages, "error", "Tu sesión expiró. Inicia sesión nuevamente.");
      return;
    }

    const nombre = document.getElementById("prod-nombre").value.trim();
    const descripcion = document.getElementById("prod-descripcion").value.trim();
    const precio = Number(document.getElementById("prod-precio").value);
    const stock = Number(document.getElementById("prod-stock").value);

    if (!nombre || Number.isNaN(precio) || Number.isNaN(stock)) {
      renderMessage(
        productMessages,
        "error",
        "Nombre, precio y stock son obligatorios y deben ser válidos."
      );
      return;
    }

    try {
      await apiRequest("/productos.php", {
        method: "POST",
        body: JSON.stringify({
          nombre,
          descripcion,
          precio,
          stock,
          vendedor_id: vendor.id_vendedor,
        }),
      });

      productForm.reset();
      await loadProducts();
      renderMessage(productMessages, "success", "Producto publicado correctamente.");
    } catch (error) {
      renderMessage(
        productMessages,
        "error",
        error.message || "No se pudo publicar el producto."
      );
    }
  }

  async function loadProducts() {
    const vendor = getVendorSession();
    if (!vendor) return;

    productsTableBody.innerHTML = "";

    try {
      const payload = await apiRequest(
        `/productos.php?vendedor_id=${encodeURIComponent(vendor.id_vendedor)}`
      );
      const productos = payload?.productos || payload || [];

      if (!productos.length) {
        appendEmptyRow(productsTableBody);
        return;
      }

      for (const p of productos) {
        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td>${p.id_producto}</td>
          <td>${p.nombre}</td>
          <td>${p.descripcion ?? ""}</td>
          <td>$${Number(p.precio).toLocaleString("es-CO")}</td>
          <td>${p.stock}</td>
        `;
        productsTableBody.appendChild(tr);
      }
    } catch (error) {
      appendEmptyRow(productsTableBody);
      console.error("Error al cargar productos:", error);
    }
  }

  async function loadSales() {
    const vendor = getVendorSession();
    if (!vendor) return;

    salesTableBody.innerHTML = "";

    try {
      const payload = await apiRequest(
        `/ventas.php?vendedor_id=${encodeURIComponent(vendor.id_vendedor)}`
      );
      const ventas = payload?.ventas || payload || [];

      if (!ventas.length) {
        appendEmptyRow(salesTableBody);
        return;
      }

      for (const v of ventas) {
        const tr = document.createElement("tr");
        const fecha = v.fecha ? new Date(v.fecha) : null;
        const fechaStr = fecha
          ? fecha.toLocaleString("es-CO", {
              dateStyle: "short",
              timeStyle: "short",
            })
          : "";
        tr.innerHTML = `
          <td>${v.id_venta}</td>
          <td>${fechaStr}</td>
          <td>${v.cliente_nombre ?? ""}</td>
          <td>${v.items ?? 0}</td>
          <td>$${Number(v.total).toLocaleString("es-CO")}</td>
        `;
        salesTableBody.appendChild(tr);
      }
    } catch (error) {
      appendEmptyRow(salesTableBody);
      console.error("Error al cargar ventas:", error);
    }
  }

  function appendEmptyRow(tbody) {
    if (!rowEmptyTemplate) return;
    const clone = rowEmptyTemplate.content.cloneNode(true);
    tbody.appendChild(clone);
  }

  function handleLogout() {
    clearVendorSession();
    setSection("auth");
    clearMessages();
  }

  function registerEventListeners() {
    if (loginForm) {
      loginForm.addEventListener("submit", handleLogin);
    }
    if (registerForm) {
      registerForm.addEventListener("submit", handleRegister);
    }
    if (productForm) {
      productForm.addEventListener("submit", handleCreateProduct);
    }
    if (showRegisterBtn) {
      showRegisterBtn.addEventListener("click", () => {
        clearMessages();
        setSection("register");
      });
    }
    if (showLoginBtn) {
      showLoginBtn.addEventListener("click", () => {
        clearMessages();
        setSection("auth");
      });
    }
    if (refreshProductsBtn) {
      refreshProductsBtn.addEventListener("click", () => {
        loadProducts();
      });
    }
    if (refreshSalesBtn) {
      refreshSalesBtn.addEventListener("click", () => {
        loadSales();
      });
    }
    if (logoutBtn) {
      logoutBtn.addEventListener("click", handleLogout);
    }
  }

  async function init() {
    registerEventListeners();

    const vendor = getVendorSession();
    if (vendor) {
      vendorNameEl.textContent = vendor.nombre || vendor.documento;
      setSection("dashboard");
      await Promise.all([loadProducts(), loadSales()]);
    } else {
      setSection("auth");
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();