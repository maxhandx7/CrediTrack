import React, { useState, useEffect } from "react";
import api, { getPayments, getLoans, getSchedules } from "../service/api";

const Payments = () => {
  const [payments, setPayments] = useState([]);
  const [loans, setLoans] = useState([]);
  const [schedules, setSchedules] = useState([]);
  const [showModal, setShowModal] = useState(false);
  const [formData, setFormData] = useState({
    loan_id: "",
    schedule_id: "",
    amount: "",
    date: "",
    notes: "",
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchAllData();
  }, []);

  const fetchAllData = async () => {
    try {
      const [paymentsRes, loansRes, schedulesRes] = await Promise.all([
        getPayments(),
        getLoans(),
        getSchedules(),
      ]);
      setPayments(paymentsRes.data);
      setLoans(loansRes.data);
      setSchedules(schedulesRes.data);
    } catch (error) {
      console.error("Error al cargar datos:", error);
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      // 1️⃣ Registrar el pago
      await api.post("/payments", formData);

      // 2️⃣ Marcar la cuota como pagada
      await api.put(`/schedules/${formData.schedule_id}`, {
        status: "pagado",
      });

      // 3️⃣ Verificar si todas las cuotas del préstamo están pagadas
      const { data: loanSchedules } = await api.get("/schedules");
      const loanSpecific = loanSchedules.filter(
        (s) => s.loan_id === parseInt(formData.loan_id)
      );
      const allPaid = loanSpecific.every((s) => s.status === "pagado");

      if (allPaid) {
        await api.put(`/loans/${formData.loan_id}`, { status: "pagado" });
      }

      // 4️⃣ Refrescar datos
      await fetchAllData();

      setShowModal(false);
      setFormData({
        loan_id: "",
        schedule_id: "",
        amount: "",
        date: "",
        notes: "",
      });
    } catch (error) {
      console.error("Error al registrar pago:", error);
      alert("No se pudo registrar el pago.");
    }
  };

  const handleDelete = async (id) => {
    if (!confirm("¿Seguro que deseas eliminar este pago?")) return;
    try {
      await api.delete(`/payments/${id}`);
      fetchAllData();
    } catch (error) {
      console.error("Error al eliminar pago:", error);
    }
  };

  const filteredSchedules = schedules.filter(
    (s) =>
      s.loan_id === parseInt(formData.loan_id) && s.status !== "pagado"
  );

  if (loading) return (
            <div className="text-center py-4">
              <div className="spinner-border text-primary"></div>
              <p className="mt-2">Cargando pagos...</p>
            </div>
          );

  return (
    <div>
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h2 className="h3 mb-0">Gestión de Pagos</h2>
        <button className="btn btn-primary" onClick={() => setShowModal(true)}>
          <i className="fas fa-plus me-2"></i> Nuevo Pago
        </button>
      </div>

      <div className="card border-0 shadow-sm">
        <div className="card-body">
          <div className="table-responsive">
            <table className="table table-hover">
              <thead>
                <tr>
                  <th>Préstamo</th>
                  <th>Cliente</th>
                  <th>Monto</th>
                  <th>Fecha</th>
                  <th>Notas</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                {payments.length > 0 ? (
                  payments.map((p) => (
                    <tr key={p.id}>
                      <td>#{p.loan_id}</td>
                      <td>{p.loan?.client?.name || "Sin cliente"}</td>
                      <td>${parseFloat(p.amount).toLocaleString()}</td>
                      <td>{p.date}</td>
                      <td>{p.notes || "-"}</td>
                      <td>
                        <button
                          className="btn btn-sm btn-outline-danger"
                          onClick={() => handleDelete(p.id)}
                        >
                          <i className="fas fa-trash"></i>
                        </button>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="6" className="text-center">
                      No hay pagos registrados.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Modal para registrar pago */}
      {showModal && (
        <div className="modal show d-block" tabIndex="-1">
          <div className="modal-dialog modal-md">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Registrar Pago</h5>
                <button
                  type="button"
                  className="btn-close"
                  onClick={() => setShowModal(false)}
                ></button>
              </div>
              <form onSubmit={handleSubmit}>
                <div className="modal-body">
                  {/* Select préstamo */}
                  <div className="mb-3">
                    <label className="form-label">Préstamo</label>
                    <select
                      className="form-select"
                      value={formData.loan_id}
                      onChange={(e) =>
                        setFormData({
                          ...formData,
                          loan_id: e.target.value,
                          schedule_id: "",
                        })
                      }
                      required
                    >
                      <option value="">Seleccione...</option>
                      {loans.map((loan) => (
                        <option key={loan.id} value={loan.id}>
                          #{loan.id} - {loan.client?.name} ($
                          {loan.amount.toLocaleString()})
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Select cuota */}
                  <div className="mb-3">
                    <label className="form-label">Cuota a pagar</label>
                    <select
                      className="form-select"
                      value={formData.schedule_id}
                      onChange={(e) =>
                        setFormData({ ...formData, schedule_id: e.target.value })
                      }
                      required
                      disabled={!formData.loan_id}
                    >
                      <option value="">Seleccione...</option>
                      {filteredSchedules.map((s) => (
                        <option key={s.id} value={s.id}>
                          {new Date(s.scheduled_date).toLocaleDateString()} - $
                          {s.amount_due.toLocaleString()}
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Monto */}
                  <div className="mb-3">
                    <label className="form-label">Monto</label>
                    <input
                      type="number"
                      className="form-control"
                      value={formData.amount}
                      onChange={(e) =>
                        setFormData({ ...formData, amount: e.target.value })
                      }
                      required
                    />
                  </div>

                  {/* Fecha */}
                  <div className="mb-3">
                    <label className="form-label">Fecha</label>
                    <input
                      type="date"
                      className="form-control"
                      value={formData.date}
                      onChange={(e) =>
                        setFormData({ ...formData, date: e.target.value })
                      }
                      required
                    />
                  </div>

                  {/* Notas */}
                  <div className="mb-3">
                    <label className="form-label">Notas</label>
                    <textarea
                      className="form-control"
                      rows="2"
                      value={formData.notes}
                      onChange={(e) =>
                        setFormData({ ...formData, notes: e.target.value })
                      }
                    ></textarea>
                  </div>
                </div>

                <div className="modal-footer">
                  <button
                    type="button"
                    className="btn btn-secondary"
                    onClick={() => setShowModal(false)}
                  >
                    Cancelar
                  </button>
                  <button type="submit" className="btn btn-primary">
                    Guardar Pago
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Payments;
