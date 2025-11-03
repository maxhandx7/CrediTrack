import React, { useState, useEffect } from "react";
import StatsCard from "../components/StatsCard";
import { getLoans, getPayments, getSchedules, getClients } from "../service/api";

const Dashboard = () => {
  const [stats, setStats] = useState({
    totalPrestado: 0,
    totalRecuperado: 0,
    interesGenerado: 0,
    clientesMorosos: 0,
    prestamosActivos: 0,
    rentabilidad: "0%",
  });

  const [recentLoans, setRecentLoans] = useState([]);
  const [loading, setLoading] = useState(true);
  const [reminders, setReminders] = useState([]);

  useEffect(() => {
    const loadDashboardData = async () => {
      try {
        const [loansRes, paymentsRes, schedulesRes, clientsRes] = await Promise.all([
          getLoans(),
          getPayments(),
          getSchedules(),
          getClients(),
        ]);

        const loans = loansRes.data;
        const payments = paymentsRes.data;
        const systemUsers = clientsRes.data;


        const systemUserIds = new Set(systemUsers.map(user => user.id));
        const res = await getSchedules();
        const filteredReminders = res.data
          .filter(item => item.status === "pendiente")
          .filter(item => item.loan && systemUserIds.has(item.loan.client_id))
          .slice(0, 3)
          .map(item => ({
            id: item.id,
            loan_id: item.loan_id,
            scheduled_date: item.scheduled_date,
            amount_due: item.amount_due,
            status: item.status,
            client: item.loan ? item.loan.client : { name: "Usuario del Sistema" },
          }));

        setReminders(filteredReminders);


        // 📊 Cálculos de estadísticas
        const totalPrestado = loans.reduce((sum, l) => sum + parseFloat(l.amount || 0), 0);
        const totalRecuperado = payments.reduce(
          (sum, p) => sum + parseFloat(p.amount || 0),
          0
        );

        const interesGenerado = loans.reduce(
          (sum, l) => sum + (parseFloat(l.amount || 0) * parseFloat(l.interest_rate || 0) / 100),
          0
        );

        const prestamosActivos = loans.filter(
          (l) => l.status === "activo"
        ).length;

        const clientesMorosos = loans.filter(
          (l) => l.status === "atrasado"
        ).length;

        const rentabilidad =
          totalPrestado > 0
            ? `${((interesGenerado / totalPrestado) * 100).toFixed(2)}%`
            : "0.00%";

        // 🧾 Últimos préstamos
        setStats({
          totalPrestado,
          totalRecuperado,
          interesGenerado,
          clientesMorosos,
          prestamosActivos,
          rentabilidad,
        });

        setRecentLoans(loans.slice(-5).reverse());
      } catch (error) {
        console.error("❌ Error al cargar el Dashboard:", error);
      } finally {
        setLoading(false);
      }
    };

    loadDashboardData();
  }, []);

  if (loading) {
    return (
      <div className="d-flex justify-content-center align-items-center" style={{ height: '50vh' }}>
        <div className="text-center">
          <div className="spinner-border text-primary" role="status">
            <span className="visually-hidden">Cargando...</span>
          </div>
          <p className="mt-2">Cargando estadísticas...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="container-fluid py-4">
      {/* Header */}
      <div className="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h1 className="h2 mb-1">Dashboard</h1>
          <p className="text-muted">Resumen general de tu cartera de préstamos</p>
        </div>
        <div className="d-flex gap-2">

        </div>
      </div>

      {/* Estadísticas Mejoradas */}
      <div className="container-fluid mb-4">
        <div className="row g-4">

          {/* Primera fila */}
          <div className="col-xl-4 col-md-6 col-sm-12">
            <StatsCard
              title="Total Prestado"
              value={`$${Number(stats.totalPrestado).toLocaleString()}`}
              icon="fa-hand-holding-usd"
              color="primary"
              trend="up"
              trendValue="12%"
            />
          </div>

          <div className="col-xl-4 col-md-6 col-sm-12">
            <StatsCard
              title="Total Recuperado"
              value={`$${Number(stats.totalRecuperado).toLocaleString()}`}
              icon="fa-money-bill-wave"
              color="success"
              trend="up"
              trendValue="8%"
            />
          </div>

          <div className="col-xl-4 col-md-6 col-sm-12">
            <StatsCard
              title="Interés Generado"
              value={`$${Number(stats.interesGenerado).toLocaleString()}`}
              icon="fa-chart-line"
              color="warning"
              trend="up"
              trendValue="15%"
            />
          </div>

          {/* Segunda fila */}
          <div className="col-xl-4 col-md-6 col-sm-12">
            <StatsCard
              title="Préstamos Activos"
              value={stats.prestamosActivos}
              icon="fa-file-contract"
              color="info"
            />
          </div>

          <div className="col-xl-4 col-md-6 col-sm-12">
            <StatsCard
              title="Clientes Morosos"
              value={stats.clientesMorosos}
              icon="fa-exclamation-triangle"
              color="danger"
              trend="down"
              trendValue="5%"
            />
          </div>

          <div className="col-xl-4 col-md-6 col-sm-12">
            <StatsCard
              title="Rentabilidad"
              value={stats.rentabilidad}
              icon="fa-percentage"
              color="success"
              trend="up"
              trendValue="2%"
            />
          </div>

        </div>
      </div>


      {/* Contenido Principal */}
      <div className="row">
        {/* Préstamos Recientes - Más Ancho */}
        <div className="col-lg-8">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-header bg-white d-flex justify-content-between align-items-center py-3">
              <h5 className="mb-0">
                <i className="fas fa-clock text-primary me-2"></i>
                Préstamos Recientes
              </h5>
              <a href="/loans" className="btn btn-sm btn-outline-primary">
                Ver todos
              </a>
            </div>
            <div className="card-body">
              {recentLoans.length > 0 ? (
                <div className="table-responsive">
                  <table className="table table-hover align-middle">
                    <thead className="table-light">
                      <tr>
                        <th>Cliente</th>
                        <th>Monto</th>
                        <th>Tasa de Interés</th>
                        <th>Fecha Venc.</th>
                        <th>Estado</th>
                      </tr>
                    </thead>
                    <tbody>
                      {recentLoans.map((loan) => (
                        <tr key={loan.id}>
                          <td>
                            <div className="d-flex align-items-center">
                              <div className="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                <i className="fas fa-user text-primary fs-6"></i>
                              </div>
                              <div>
                                <span className="fw-medium">{loan.client?.name || "—"}</span>
                                <br />
                                <small className="text-muted">{loan.client?.email || ""}</small>
                              </div>
                            </div>
                          </td>
                          <td className="fw-bold">${loan.amount?.toLocaleString() || "0"}</td>
                          <td>
                            <span className="badge bg-light text-dark">
                              {loan.interest_rate || "0"}%
                            </span>
                          </td>
                          <td>
                            {loan.due_date ? (
                              <div>
                                <div className="fw-medium">
                                  {new Date(loan.due_date).toLocaleDateString()}
                                </div>
                                <small className={`badge ${new Date(loan.due_date) < new Date()
                                  ? 'bg-danger'
                                  : 'bg-warning'
                                  }`}>
                                  {new Date(loan.due_date) < new Date() ? 'Vencido' : 'Próximo'}
                                </small>
                              </div>
                            ) : (
                              "—"
                            )}
                          </td>
                          <td>
                            <span
                              className={`badge ${loan.status === "activo"
                                ? "bg-success"
                                : loan.status === "atrasado"
                                  ? "bg-danger"
                                  : "bg-secondary"
                                }`}
                            >
                              {loan.status || "pendiente"}
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="text-center py-5">
                  <i className="fas fa-file-invoice-dollar display-4 text-muted mb-3"></i>
                  <p className="text-muted">No hay préstamos recientes.</p>
                  <a href="/loans" className="btn btn-primary">
                    <i className="fas fa-plus me-2"></i>
                    Crear primer préstamo
                  </a>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Recordatorios - Rediseñado */}
        <div className="col-lg-4">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-header bg-white d-flex justify-content-between align-items-center py-3">
              <h5 className="mb-0">
                <i className="fas fa-bell text-warning me-2"></i>
                Recordatorios del Sistema
              </h5>
              <span className="badge bg-warning">{reminders.length}</span>
            </div>
            <div className="card-body p-0">
              {reminders.length > 0 ? (
                <div className="list-group list-group-flush">
                  {reminders.map((reminder, index) => (
                    <div
                      key={reminder.id}
                      className={`list-group-item border-0 px-4 py-3 ${index !== reminders.length - 1 ? 'border-bottom' : ''
                        }`}
                    >
                      <div className="d-flex align-items-start">
                        <div className="avatar-sm bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3 mt-1">
                          <i className="fas fa-calendar-day text-warning"></i>
                        </div>
                        <div className="flex-grow-1">
                          <div className="d-flex justify-content-between align-items-start mb-1">
                            <h6 className="mb-0 fw-medium">Pago Pendiente</h6>
                            <small className="text-muted">
                              {new Date(reminder.scheduled_date).toLocaleDateString()}
                            </small>
                          </div>
                          <p className="mb-1 text-dark">
                            ${reminder.amount_due?.toLocaleString() || "0"} - {reminder.client?.name || "Usuario del Sistema"}
                          </p>
                          <div className="d-flex justify-content-between align-items-center">
                            <small className={`badge ${reminder.status === 'pendiente'
                              ? 'bg-warning'
                              : reminder.status === 'pagado'
                                ? 'bg-success'
                                : 'bg-secondary'
                              }`}>
                              {reminder.status || 'pendiente'}
                            </small>

                          </div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-5">
                  <i className="fas fa-bell-slash display-4 text-muted mb-3"></i>
                  <p className="text-muted mb-2">No hay recordatorios próximos</p>
                  <small className="text-muted">Los recordatorios aparecerán aquí automáticamente</small>
                </div>
              )}
            </div>
            {reminders.length > 0 && (
              <div className="card-footer bg-white border-0">
                <a href="/calendar" className="btn btn-outline-primary w-100">
                  <i className="fas fa-calendar-alt me-2"></i>
                  Ver todos
                </a>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Métricas Adicionales */}
      <div className="row mt-4">
        <div className="col-md-6">
          <div className="card border-0 shadow-sm">
            <div className="card-body">
              <h6 className="card-title">Distribución de Préstamos</h6>
              <div className="d-flex justify-content-around text-center">
                <div>
                  <div className="h4 text-primary mb-1">{stats.prestamosActivos}</div>
                  <small className="text-muted">Activos</small>
                </div>
                <div>
                  <div className="h4 text-success mb-1">
                    {recentLoans.length - stats.prestamosActivos - stats.clientesMorosos}
                  </div>
                  <small className="text-muted">Completados</small>
                </div>
                <div>
                  <div className="h4 text-danger mb-1">{stats.clientesMorosos}</div>
                  <small className="text-muted">Morosos</small>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div className="col-md-6">
          <div className="card border-0 shadow-sm">
            <div className="card-body">
              <h6 className="card-title">Resumen de Pagos</h6>
              <div className="d-flex justify-content-between">
                <span>Este mes:</span>
                <strong className="text-success">
                  +${(stats.totalRecuperado).toLocaleString()}
                </strong>
              </div>
              <div className="progress mt-2" style={{ height: '6px' }}>
                <div
                  className="progress-bar bg-success"
                  style={{ width: `${Math.round(stats.rentabilidad).toLocaleString()}%` }}
                ></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;