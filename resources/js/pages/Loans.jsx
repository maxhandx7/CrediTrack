import React, { useState, useEffect } from "react";
import { getLoans, getLoanById } from "../service/api";
import api from "../service/api";
import "bootstrap/dist/css/bootstrap.min.css";

const Loans = () => {
  const [loans, setLoans] = useState([]);
  const [clients, setClients] = useState([]);
  const [selectedLoan, setSelectedLoan] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [showDetail, setShowDetail] = useState(false);
  const [loading, setLoading] = useState(true);
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

  const handleShowDetail = async (id) => {
    try {
      const { data } = await getLoanById(id);
      setSelectedLoan(data);
      setShowDetail(true);
    } catch (error) {
      console.error("Error al obtener detalle del préstamo:", error);
    }
  };

  const calculateLoanProgress = (loan) => {
    const totalCuotas = loan.schedules?.length || 0;
    const pagadas = loan.payments?.length || 0;
    const saldoPagado = loan.payments?.reduce(
      (acc, p) => acc + parseFloat(p.amount),
      0
    );
    const saldoTotal = parseFloat(loan.amount);
    const saldoRestante = saldoTotal - saldoPagado;

    return { totalCuotas, pagadas, saldoPagado, saldoRestante };
  };

  if (loading)
    return (
      <div className="loading-container">
        <div className="spinner-border text-light"></div>
        <p className="mt-3 text-light">Cargando préstamos...</p>
      </div>
    );

  return (
    <div className="container mt-5">
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h2 className="fw-bold text-primary">
          <i className="fas fa-hand-holding-usd me-2"></i> Gestión de Préstamos
        </h2>
        <button className="btn btn-primary" onClick={() => setShowModal(true)}>
          <i className="fas fa-plus me-2"></i> Nuevo Préstamo
        </button>
      </div>

      <div className="card border-0 shadow-lg rounded-4">
        <div className="card-body">
          <div className="table-responsive">
            <table className="table table-hover align-middle">
              <thead className="table-primary">
                <tr>
                  <th>Cliente</th>
                  <th>Monto</th>
                  <th>Tasa</th>
                  <th>Inicio</th>
                  <th>Vencimiento</th>
                  <th>Progreso</th>
                  <th>Saldo</th>
                  <th>Estado</th>
                  <th className="text-center">Acciones</th>
                </tr>
              </thead>
              <tbody>
                {loans.length > 0 ? (
                  loans.map((loan) => {
                    const progress = calculateLoanProgress(loan);
                    return (
                      <tr key={loan.id}>
                        <td>{loan.client?.name || "Sin cliente"}</td>
                        <td>${parseFloat(loan.amount).toLocaleString("es-CO")}</td>
                        <td>{loan.interest_rate}%</td>
                        <td>{loan.start_date}</td>
                        <td>{loan.due_date}</td>
                        <td>
                          {progress.pagadas}/{progress.totalCuotas}
                        </td>
                        <td>
                          ${progress.saldoRestante.toLocaleString("es-CO")}
                        </td>
                        <td>
                          <span
                            className={`badge ${
                              loan.status === "activo" ? "bg-success" : "bg-secondary"
                            }`}
                          >
                            {loan.status}
                          </span>
                        </td>
                        <td className="text-center">
                          <button
                            className="btn btn-sm btn-outline-info me-2"
                            onClick={() => handleShowDetail(loan.id)}
                          >
                            <i className="fas fa-eye"></i>
                          </button>
                          <button
                            className="btn btn-sm btn-outline-danger"
                            onClick={() => handleDelete(loan.id)}
                          >
                            <i className="fas fa-trash"></i>
                          </button>
                        </td>
                      </tr>
                    );
                  })
                ) : (
                  <tr>
                    <td colSpan="9" className="text-center text-muted py-4">
                      <i className="fas fa-info-circle me-2"></i>
                      No hay préstamos registrados.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Modal de Detalle */}
      {showDetail && selectedLoan && (
        <div className="modal show d-block fade" tabIndex="-1">
          <div className="modal-dialog modal-lg modal-dialog-centered">
            <div className="modal-content border-0 shadow-lg rounded-4">
              <div className="modal-header bg-primary text-white">
                <h5 className="modal-title">
                  <i className="fas fa-file-invoice-dollar me-2"></i>
                  Detalle del préstamo #{selectedLoan.id}
                </h5>
                <button
                  type="button"
                  className="btn-close btn-close-white"
                  onClick={() => setShowDetail(false)}
                ></button>
              </div>
              <div className="modal-body">
                <div className="row">
                  <div className="col-md-6">
                    <h6 className="fw-bold mb-3 text-primary">Información del Cliente</h6>
                    <p>
                      <strong>Nombre:</strong> {selectedLoan.client?.name} <br />
                      <strong>Documento:</strong> {selectedLoan.client?.document || "N/A"} <br />
                      <strong>Teléfono:</strong> {selectedLoan.client?.phone || "No disponible"}<br />
                      <strong>Email:</strong> {selectedLoan.client?.email || "No disponible"}<br />
                      <strong>Dirección:</strong> {selectedLoan.client?.address || "No disponible"}<br />
                      <strong>Notas:</strong> {selectedLoan.client?.notes || "—"}
                    </p>
                  </div>
                  <div className="col-md-6">
                    <h6 className="fw-bold mb-3 text-primary">Detalles del Préstamo</h6>
                    <p>
                      <strong>Monto:</strong> $
                      {parseFloat(selectedLoan.amount).toLocaleString("es-CO")} <br />
                      <strong>Interés:</strong> {selectedLoan.interest_rate}% (
                      {selectedLoan.interest_type}) <br />
                      <strong>Frecuencia:</strong> {selectedLoan.payment_frequency} <br />
                      <strong>Estado:</strong>{" "}
                      <span
                        className={`badge ${
                          selectedLoan.status === "activo" ? "bg-success" : "bg-secondary"
                        }`}
                      >
                        {selectedLoan.status}
                      </span>
                    </p>
                  </div>
                </div>

                <hr />

                <h6 className="fw-bold text-primary mt-3">Pagos Realizados</h6>
                {selectedLoan.payments?.length > 0 ? (
                  <ul className="list-group mb-4">
                    {selectedLoan.payments.map((p) => (
                      <li
                        key={p.id}
                        className="list-group-item d-flex justify-content-between align-items-center"
                      >
                        <span>
                          {p.date} — $
                          {parseFloat(p.amount).toLocaleString("es-CO")}
                        </span>
                        <span className="badge bg-success">Pagado</span>
                      </li>
                    ))}
                  </ul>
                ) : (
                  <p className="text-muted">No hay pagos registrados.</p>
                )}
              </div>

              <div className="modal-footer">
                <button
                  className="btn btn-secondary"
                  onClick={() => setShowDetail(false)}
                >
                  Cerrar
                </button>
              </div>
            </div>
          </div>
        </div>
      )}


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
