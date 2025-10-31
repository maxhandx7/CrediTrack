// src/pages/Analytics.jsx
import React, { useState, useEffect } from 'react';
import { getAnalytics, getAnalyticsExport } from '../service/api';
import { Bar, Pie, Doughnut, Line } from 'react-chartjs-2';
import { 
  Chart as ChartJS, 
  CategoryScale, 
  LinearScale, 
  BarElement, 
  Title, 
  Tooltip, 
  Legend, 
  ArcElement,
  PointElement,
  LineElement
} from 'chart.js';
import 'bootstrap/dist/css/bootstrap.min.css';

ChartJS.register(
  CategoryScale, 
  LinearScale, 
  BarElement, 
  Title, 
  Tooltip, 
  Legend, 
  ArcElement,
  PointElement,
  LineElement
);

// Paleta de colores mejorada
const COLORS = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4', '#F97316', '#84CC16'];

const Analytics = () => {
  const [analytics, setAnalytics] = useState(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState('overview');

  useEffect(() => {
    fetchAnalytics();
  }, []);

  const fetchAnalytics = async () => {
    try {
      const { data } = await getAnalytics();
      setAnalytics(data);
    } catch (error) {
      console.error('Error cargando analítica:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleExport = async () => {
    try {
      const response = await getAnalyticsExport();
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', 'analytics.xlsx');
      document.body.appendChild(link);
      link.click();
      link.remove();
    } catch (error) {
      console.error('Error exportando analítica:', error);
    }
  };

  // Opciones comunes para gráficos
  const chartOptions = {
    responsive: true,
    plugins: {
      legend: {
        position: 'bottom',
      },
    },
    maintainAspectRatio: false,
  };

  if (loading) {
    return (
      <div className="d-flex justify-content-center align-items-center" style={{ height: '50vh' }}>
        <div className="text-center">
          <div className="spinner-border text-primary" role="status">
            <span className="visually-hidden">Cargando...</span>
          </div>
          <p className="mt-2">Cargando analítica...</p>
        </div>
      </div>
    );
  }

  if (!analytics) {
    return (
      <div className="container py-4">
        <div className="alert alert-warning text-center" role="alert">
          <h4 className="alert-heading">No hay datos disponibles</h4>
          <p>No se pudieron cargar los datos de analítica. Intente nuevamente más tarde.</p>
        </div>
      </div>
    );
  }

  // Desestructuramos con valores por defecto
  const {
    totals = {},
    top_clients = [],
    payment_frequency_summary = [],
    interest_summary = [],
  } = analytics;

  // Datos para clientes principales
  const clientsData = {
    labels: top_clients.map(c => c.name ?? 'Sin nombre'),
    datasets: [
      {
        label: 'Número de Préstamos',
        data: top_clients.map(c => c.loans_count ?? 0),
        backgroundColor: COLORS.slice(0, top_clients.length),
        borderColor: COLORS.slice(0, top_clients.length),
        borderWidth: 1,
      },
    ],
  };

  // Datos para frecuencia de pagos
  const frequencyData = {
    labels: payment_frequency_summary.map(p => {
      const frequency = p.payment_frequency ?? 'Desconocido';
      return frequency.charAt(0).toUpperCase() + frequency.slice(1);
    }),
    datasets: [
      {
        label: 'Cantidad de Pagos',
        data: payment_frequency_summary.map(p => p.total ?? 0),
        backgroundColor: COLORS,
        borderColor: COLORS.map(color => color.replace('0.8', '1')),
        borderWidth: 1,
      },
    ],
  };

  // Datos para intereses
  const interestData = {
    labels: interest_summary.map(i => i.client ?? 'Desconocido'),
    datasets: [
      {
        label: 'Monto de Interés ($)',
        data: interest_summary.map(i => i.interest_amount ?? 0),
        backgroundColor: COLORS,
        borderColor: COLORS.map(color => color.replace('0.8', '1')),
        borderWidth: 1,
      },
    ],
  };

  // Datos para gráfico de estado de préstamos
  const loanStatusData = {
    labels: ['Activos', 'Pagados'],
    datasets: [
      {
        data: [totals.active_loans ?? 0, totals.paid_loans ?? 0],
        backgroundColor: ['#10B981', '#3B82F6'],
        borderColor: ['#10B981', '#3B82F6'],
        borderWidth: 1,
      },
    ],
  };

  // Datos para resumen financiero
  const financialSummaryData = {
    labels: ['Monto Total', 'Pagado', 'Restante'],
    datasets: [
      {
        label: 'Montos ($)',
        data: [totals.total_amount ?? 0, totals.total_paid ?? 0, totals.total_remaining ?? 0],
        backgroundColor: ['#3B82F6', '#10B981', '#F59E0B'],
        borderColor: ['#3B82F6', '#10B981', '#F59E0B'],
        borderWidth: 1,
      },
    ],
  };

  return (
    <div className="container-fluid py-4">
      <div className="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h1 className="h2 mb-1">Panel de Analítica</h1>
          <p className="text-muted">Resumen completo de la actividad de préstamos</p>
        </div>
        <button className="btn btn-primary d-flex align-items-center" onClick={handleExport}>
          <i className="bi bi-download me-2"></i>
          Exportar Excel
        </button>
      </div>

      {/* Navegación por pestañas */}
      <ul className="nav nav-tabs mb-4">
        <li className="nav-item">
          <button 
            className={`nav-link ${activeTab === 'overview' ? 'active' : ''}`}
            onClick={() => setActiveTab('overview')}
          >
            Resumen General
          </button>
        </li>
        <li className="nav-item">
          <button 
            className={`nav-link ${activeTab === 'clients' ? 'active' : ''}`}
            onClick={() => setActiveTab('clients')}
          >
            Clientes
          </button>
        </li>
        <li className="nav-item">
          <button 
            className={`nav-link ${activeTab === 'payments' ? 'active' : ''}`}
            onClick={() => setActiveTab('payments')}
          >
            Pagos
          </button>
        </li>
      </ul>

      {activeTab === 'overview' && (
        <div className="row">
          {/* Tarjetas de métricas principales */}
          <div className="col-md-3 mb-4">
            <div className="card border-0 shadow-sm h-100">
              <div className="card-body">
                <div className="d-flex justify-content-between align-items-start">
                  <div>
                    <h6 className="card-title text-muted mb-2">Total Clientes</h6>
                    <h3 className="mb-0">{totals.clients ?? 0}</h3>
                  </div>
                  <div className="bg-primary bg-opacity-10 p-2 rounded">
                    <i className="bi bi-people text-primary"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="col-md-3 mb-4">
            <div className="card border-0 shadow-sm h-100">
              <div className="card-body">
                <div className="d-flex justify-content-between align-items-start">
                  <div>
                    <h6 className="card-title text-muted mb-2">Total Préstamos</h6>
                    <h3 className="mb-0">{totals.loans ?? 0}</h3>
                  </div>
                  <div className="bg-success bg-opacity-10 p-2 rounded">
                    <i className="bi bi-cash-coin text-success"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="col-md-3 mb-4">
            <div className="card border-0 shadow-sm h-100">
              <div className="card-body">
                <div className="d-flex justify-content-between align-items-start">
                  <div>
                    <h6 className="card-title text-muted mb-2">Pagos Vencidos</h6>
                    <h3 className="mb-0">{totals.overdue_payments ?? 0}</h3>
                  </div>
                  <div className="bg-danger bg-opacity-10 p-2 rounded">
                    <i className="bi bi-exclamation-triangle text-danger"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="col-md-3 mb-4">
            <div className="card border-0 shadow-sm h-100">
              <div className="card-body">
                <div className="d-flex justify-content-between align-items-start">
                  <div>
                    <h6 className="card-title text-muted mb-2">Monto Total</h6>
                    <h3 className="mb-0">${Number(totals.total_amount).toLocaleString() ?? 0 }</h3>
                  </div>
                  <div className="bg-info bg-opacity-10 p-2 rounded">
                    <i className="bi bi-currency-dollar text-info"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Gráficos de resumen */}
          <div className="col-lg-6 mb-4">
            <div className="card border-0 shadow-sm h-100">
              <div className="card-header bg-white">
                <h5 className="card-title mb-0">Estado de Préstamos</h5>
              </div>
              <div className="card-body">
                <div style={{ height: '300px' }}>
                  <Doughnut 
                    data={loanStatusData} 
                    options={{
                      ...chartOptions,
                      plugins: {
                        ...chartOptions.plugins,
                        tooltip: {
                          callbacks: {
                            label: function(context) {
                              const total = context.dataset.data.reduce((a, b) => a + b, 0);
                              const value = context.parsed;
                              const percentage = Math.round((value / total) * 100);
                              return `${context.label}: ${value} (${percentage}%)`;
                            }
                          }
                        }
                      }
                    }} 
                  />
                </div>
              </div>
            </div>
          </div>

          <div className="col-lg-6 mb-4">
            <div className="card border-0 shadow-sm h-100">
              <div className="card-header bg-white">
                <h5 className="card-title mb-0">Resumen Financiero</h5>
              </div>
              <div className="card-body">
                <div style={{ height: '300px' }}>
                  <Bar 
                    data={financialSummaryData} 
                    options={{
                      ...chartOptions,
                      scales: {
                        y: {
                          beginAtZero: true,
                          ticks: {
                            callback: function(value) {
                              return '$' + value.toLocaleString();
                            }
                          }
                        }
                      }
                    }} 
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {activeTab === 'clients' && (
        <div className="row">
          <div className="col-12 mb-4">
            <div className="card border-0 shadow-sm">
              <div className="card-header bg-white">
                <h5 className="card-title mb-0">Clientes Principales</h5>
              </div>
              <div className="card-body">
                {top_clients.length > 0 ? (
                  <div style={{ height: '400px' }}>
                    <Bar 
                      data={clientsData} 
                      options={{
                        ...chartOptions,
                        indexAxis: 'y',
                        scales: {
                          x: {
                            beginAtZero: true,
                            title: {
                              display: true,
                              text: 'Número de Préstamos'
                            }
                          }
                        }
                      }} 
                    />
                  </div>
                ) : (
                  <div className="text-center py-4">
                    <i className="bi bi-people display-4 text-muted"></i>
                    <p className="mt-2 text-muted">No hay clientes destacados.</p>
                  </div>
                )}
              </div>
            </div>
          </div>

          <div className="col-12">
            <div className="card border-0 shadow-sm">
              <div className="card-header bg-white">
                <h5 className="card-title mb-0">Resumen de Intereses</h5>
              </div>
              <div className="card-body">
                {interest_summary.length > 0 ? (
                  <div style={{ height: '400px' }}>
                    <Bar 
                      data={interestData} 
                      options={{
                        ...chartOptions,
                        scales: {
                          y: {
                            beginAtZero: true,
                            ticks: {
                              callback: function(value) {
                                return '$' + value.toLocaleString();
                              }
                            }
                          }
                        }
                      }} 
                    />
                  </div>
                ) : (
                  <div className="text-center py-4">
                    <i className="bi bi-pie-chart display-4 text-muted"></i>
                    <p className="mt-2 text-muted">No hay datos de intereses.</p>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {activeTab === 'payments' && (
        <div className="row">
          <div className="col-12 mb-4">
            <div className="card border-0 shadow-sm">
              <div className="card-header bg-white">
                <h5 className="card-title mb-0">Frecuencia de Pagos</h5>
              </div>
              <div className="card-body">
                {payment_frequency_summary.length > 0 ? (
                  <div className="row">
                    <div className="col-md-6">
                      <div style={{ height: '350px' }}>
                        <Pie 
                          data={frequencyData} 
                          options={{
                            ...chartOptions,
                            plugins: {
                              ...chartOptions.plugins,
                              tooltip: {
                                callbacks: {
                                  label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const value = context.parsed;
                                    const percentage = Math.round((value / total) * 100);
                                    return `${context.label}: ${value} (${percentage}%)`;
                                  }
                                }
                              }
                            }
                          }} 
                        />
                      </div>
                    </div>
                    <div className="col-md-6">
                      <div className="table-responsive">
                        <table className="table table-hover">
                          <thead>
                            <tr>
                              <th>Frecuencia</th>
                              <th className="text-end">Cantidad</th>
                              <th className="text-end">Porcentaje</th>
                            </tr>
                          </thead>
                          <tbody>
                            {payment_frequency_summary.map((item, index) => {
                              const total = payment_frequency_summary.reduce((sum, curr) => sum + (curr.total || 0), 0);
                              const percentage = total > 0 ? Math.round((item.total / total) * 100) : 0;
                              return (
                                <tr key={index}>
                                  <td>{item.payment_frequency ? item.payment_frequency.charAt(0).toUpperCase() + item.payment_frequency.slice(1) : 'Desconocido'}</td>
                                  <td className="text-end">{item.total || 0}</td>
                                  <td className="text-end">{percentage}%</td>
                                </tr>
                              );
                            })}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                ) : (
                  <div className="text-center py-4">
                    <i className="bi bi-calendar-check display-4 text-muted"></i>
                    <p className="mt-2 text-muted">No hay información de frecuencia de pagos.</p>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Analytics;   