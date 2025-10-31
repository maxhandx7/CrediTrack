import React, { useState, useEffect } from "react";
import { getLoans } from "../service/api";
import api from "../service/api";

const Loans = () => {
  const [loans, setLoans] = useState([]);
  const [clients, setClients] = useState([]);
  const [showModal, setShowModal] = useState(false);
  const [formData, setFormData] = useState({
    client_id: "",
    amount: "",
    interest_rate: "",
    interest_type: "simple",
    start_date: "",
    due_date: "",
    payment_frequency: "mensual",
    notes: "",
  });
  const [loading, setLoading] = useState(true);

  // 🔹 Obtener préstamos al montar el componente
  useEffect(() => {
    fetchLoans();
    fetchClients();
  }, []);

  const fetchLoans = async () => {
    try {
      const { data } = await getLoans();
      setLoans(data);
    } catch (error) {
      console.error("Error al obtener préstamos:", error);
    } finally {
      setLoading(false);
    }
  };

  const fetchClients = async () => {
    try {
      const { data } = await api.get("/clients");
      setClients(data);
    } catch (error) {
      console.error("Error al cargar clientes:", error);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      await api.post("/loans", formData);
      setShowModal(false);
      setFormData({
        client_id: "",
        amount: "",
        interest_rate: "",
        interest_type: "simple",
        start_date: "",
        due_date: "",
        payment_frequency: "mensual",
        notes: "",
      });
      fetchLoans();
    } catch (error) {
      console.error("Error al crear préstamo:", error);
      alert("Error al registrar el préstamo.");
    }
  };

  const handleDelete = async (id) => {
    if (!confirm("¿Seguro que deseas eliminar este préstamo?")) return;
    try {
      await api.delete(`/loans/${id}`);
      fetchLoans();
    } catch (error) {
      console.error("Error al eliminar préstamo:", error);
    }
  };

  if (loading) return (
            <div className="text-center py-4">
              <div className="spinner-border text-primary"></div>
              <p className="mt-2">Cargando préstamos...</p>
            </div>
          );

  return (
    <div>
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h2 className="h3 mb-0">Gestión de Préstamos</h2>
        <button className="btn btn-primary" onClick={() => setShowModal(true)}>
          <i className="fas fa-plus me-2"></i> Nuevo Préstamo
        </button>
      </div>

      <div className="card border-0 shadow-sm">
        <div className="card-body">
          <div className="table-responsive">
            <table className="table table-hover">
              <thead>
                <tr>
                  <th>Cliente</th>
                  <th>Monto</th>
                  <th>Tasa (%)</th>
                  <th>Tipo</th>
                  <th>Inicio</th>
                  <th>Vencimiento</th>
                  <th>Frecuencia</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                {loans.length > 0 ? (
                  loans.map((loan) => (
                    <tr key={loan.id}>
                      <td>{loan.client?.name || "Sin cliente"}</td>
                      <td>${parseFloat(loan.amount).toLocaleString()}</td>
                      <td>{loan.interest_rate}%</td>
                      <td>{loan.interest_type}</td>
                      <td>{loan.start_date}</td>
                      <td>{loan.due_date}</td>
                      <td>{loan.payment_frequency}</td>
                      <td>
                        <span
                          className={`badge ${
                            loan.status === "activo"
                              ? "bg-success"
                              : "bg-secondary"
                          }`}
                        >
                          {loan.status}
                        </span>
                      </td>
                      <td>
                        <button className="btn btn-sm btn-outline-danger" onClick={() => handleDelete(loan.id)}>
                          <i className="fas fa-trash"></i>
                        </button>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="9" className="text-center">
                      No hay préstamos registrados.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Modal para crear préstamo */}
      {showModal && (
        <div className="modal show d-block" tabIndex="-1">
          <div className="modal-dialog modal-lg">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">Registrar Préstamo</h5>
                <button
                  type="button"
                  className="btn-close"
                  onClick={() => setShowModal(false)}
                ></button>
              </div>
              <form onSubmit={handleSubmit}>
                <div className="modal-body">
                  <div className="row g-3">
                    <div className="col-md-6">
                      <label className="form-label">Cliente</label>
                      <select
                        className="form-select"
                        value={formData.client_id}
                        onChange={(e) =>
                          setFormData({ ...formData, client_id: e.target.value })
                        }
                        required
                      >
                        <option value="">Seleccione...</option>
                        {clients.map((client) => (
                          <option key={client.id} value={client.id}>
                            {client.name}
                          </option>
                        ))}
                      </select>
                    </div>
                    <div className="col-md-6">
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
                    <div className="col-md-4">
                      <label className="form-label">Tasa de interés (%)</label>
                      <input
                        type="number"
                        className="form-control"
                        value={formData.interest_rate}
                        onChange={(e) =>
                          setFormData({
                            ...formData,
                            interest_rate: e.target.value,
                          })
                        }
                        required
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label">Tipo de interés</label>
                      <select
                        className="form-select"
                        value={formData.interest_type}
                        onChange={(e) =>
                          setFormData({
                            ...formData,
                            interest_type: e.target.value,
                          })
                        }
                        required
                      >
                        <option value="simple">Simple</option>
                        <option value="compuesto">Compuesto</option>
                      </select>
                    </div>
                    <div className="col-md-4">
                      <label className="form-label">Frecuencia de pago</label>
                      <select
                        className="form-select"
                        value={formData.payment_frequency}
                        onChange={(e) =>
                          setFormData({
                            ...formData,
                            payment_frequency: e.target.value,
                          })
                        }
                        required
                      >
                        <option value="diaria">Diaria</option>
                        <option value="semanal">Semanal</option>
                        <option value="quincenal">Quincenal</option>
                        <option value="mensual">Mensual</option>
                      </select>
                    </div>
                    <div className="col-md-6">
                      <label className="form-label">Fecha de inicio</label>
                      <input
                        type="date"
                        className="form-control"
                        value={formData.start_date}
                        onChange={(e) =>
                          setFormData({
                            ...formData,
                            start_date: e.target.value,
                          })
                        }
                        required
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label">Fecha de vencimiento</label>
                      <input
                        type="date"
                        className="form-control"
                        value={formData.due_date}
                        onChange={(e) =>
                          setFormData({
                            ...formData,
                            due_date: e.target.value,
                          })
                        }
                        required
                      />
                    </div>
                    <div className="col-12">
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
                    Guardar Préstamo
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

export default Loans;
