import React, { useState, useEffect } from "react";
import StatsCard from "../components/StatsCard";
import { getLoans, getPayments, getSchedules } from "../service/api";


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
        const [loansRes, paymentsRes, schedulesRes] = await Promise.all([
          getLoans(),
          getPayments(),
          getSchedules(),
        ]);

        const loans = loansRes.data;
        const payments = paymentsRes.data;
        const schedules = schedulesRes.data;
        setReminders(schedules.slice(0, 5));

        // 📊 Cálculos de estadísticas
        const totalPrestado = loans.reduce((sum, l) => sum + l.amount, 0);
        const totalRecuperado = payments.reduce(
          (sum, p) => sum + (p.amount || 0),
          0
        );
        const interesGenerado = loans.reduce(
          (sum, l) => sum + (l.interest_rate || 0),
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
            ? ((interesGenerado / totalPrestado) * 100) + "%"
            : "0%";

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

  if (loading)
    return <div className="text-center mt-5">Cargando estadísticas...</div>;

  return (
    <div>
      <div className="d-flex justify-content-between align-items-center mb-4">

        {/*  <button className="btn btn-primary">
          <i className="fas fa-plus me-2"></i>
          Nuevo Préstamo
        </button> */}
      </div>

      {/* Estadísticas */}
      <div className="row g-4 mb-4">
        <div className="col-xl-3 col-md-6">
          <StatsCard
            title="Total Prestado"
            value={`$${Number(stats.totalPrestado).toLocaleString()}`}
            icon="fa-hand-holding-usd"
            color="primary"
          />
        </div>
        <div className="col-xl-3 col-md-6">
          <StatsCard
            title="Total Recuperado"
            value={`$${Number(stats.totalRecuperado).toLocaleString()}`}
            icon="fa-money-bill-wave"
            color="success"
          />
        </div>
        <div className="col-xl-3 col-md-6">
          <StatsCard
            title="Interés Generado"
            value={`$${Number(stats.interesGenerado).toLocaleString()}`}
            icon="fa-chart-line"
            color="warning"
          />
        </div>
        <div className="col-xl-3 col-md-6">
          <StatsCard
            title="Clientes Morosos"
            value={stats.clientesMorosos}
            icon="fa-exclamation-triangle"
            color="danger"
          />
        </div>
        <div className="col-xl-3 col-md-6">
          <StatsCard
            title="Rentabilidad"
            value={stats.rentabilidad}
            icon="fa-percentage"
            color="info"
          />
        </div>
      </div>

      {/* Tabla de préstamos recientes */}
      <div className="row">
        <div className="col-lg-8">
          <div className="card border-0 shadow-sm">
            <div className="card-header bg-white">
              <h5 className="mb-0">Préstamos Recientes</h5>
            </div>
            <div className="card-body">
              {recentLoans.length > 0 ? (
                <div className="table-responsive">
                  <table className="table table-hover">
                    <thead>
                      <tr>
                        <th>Cliente</th>
                        <th>Monto</th>
                        <th>Fecha Venc.</th>
                        <th>Estado</th>
                      </tr>
                    </thead>
                    <tbody>
                      {recentLoans.map((loan) => (
                        <tr key={loan.id}>
                          <td>{loan.client?.name || "—"}</td>
                          <td>${loan.amount.toLocaleString()}</td>
                          <td>
                            {loan.due_date
                              ? new Date(loan.due_date).toLocaleDateString()
                              : "—"}
                          </td>
                          <td>
                            <span
                              className={`badge ${loan.status === "activo"
                                ? "bg-success"
                                : loan.status === "moroso"
                                  ? "bg-danger"
                                  : "bg-secondary"
                                }`}
                            >
                              {loan.status}
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <p className="text-muted">No hay préstamos recientes.</p>
              )}
            </div>
          </div>
        </div>

        {/* Recordatorios */}
        <div className="col-lg-4">
          <div className="card border-0 shadow-sm">
            <div className="card-header bg-white">
              <h5 className="mb-0">Recordatorios</h5>
            </div>
            <div className="card-body">
              {reminders.length > 0 ? (
                <div className="list-group list-group-flush">
                  {reminders.map((r) => (
                    <div key={r.id} className="list-group-item px-0">
                      <small className="text-muted">
                        {new Date(r.scheduled_date).toLocaleDateString()}
                      </small>
                      <p className="mb-1">{r.amount_due}</p>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-muted">No hay recordatorios próximos.</p>
              )}
            </div>
          </div>
        </div>

      </div>
    </div>
  );
};

export default Dashboard;
