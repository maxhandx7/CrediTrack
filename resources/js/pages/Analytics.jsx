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
  const [dateRange, setDateRange] = useState('all'); // 'all', 'month', 'quarter', 'year'

  useEffect(() => {
    fetchAnalytics();
  }, [dateRange]);

  const fetchAnalytics = async () => {
    try {
      const { data } = await getAnalytics(dateRange);
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
      link.setAttribute('download', `analytics-${new Date().toISOString().split('T')[0]}.xlsx`);
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
    loans = [],
    total_ganado_interes = 0,
  } = analytics;


  const totalGanadoInteres = total_ganado_interes;

  const interesGeneradoSimple = loans.reduce(
    (sum, l) => sum + (parseFloat(l.amount || 0) * parseFloat(l.interest_rate || 0) / 100) * l.schedulesCount?.length,
    0
  );

  const rentabilidadReal = totals.total_amount > 0
    ? ((totalGanadoInteres / totals.total_amount) * 100).toFixed(2)
    : "0.00";

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
    labels: ['Activos', 'Pagados', 'Atrasados'],
    datasets: [
      {
        data: [
          totals.active_loans ?? 0,
          totals.paid_loans ?? 0,
          totals.overdue_loans ?? 0
        ],
        backgroundColor: ['#10B981', '#3B82F6', '#EF4444'],
        borderColor: ['#10B981', '#3B82F6', '#EF4444'],
        borderWidth: 1,
      },
    ],
  };

  // Datos para resumen financiero
  const financialSummaryData = {
    labels: ['Monto Total', 'Pagado', 'Restante', 'Interés Generado'],
    datasets: [
      {
        label: 'Montos ($)',
        data: [
          totals.total_amount ?? 0,
          totals.total_paid ?? 0,
          totals.total_remaining ?? 0,
          totalGanadoInteres
        ],
        backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#8B5CF6'],
        borderColor: ['#3B82F6', '#10B981', '#F59E0B', '#8B5CF6'],
        borderWidth: 1,
      },
    ],
  };

  // Datos para comparación de intereses
  const interestComparisonData = {
    labels: ['Interés Simple', 'Total con Interés'],
    datasets: [
      {
        label: 'Monto ($)',
        data: [interesGeneradoSimple, totalGanadoInteres],
        backgroundColor: ['#06B6D4', '#F97316'],
        borderColor: ['#06B6D4', '#F97316'],
        borderWidth: 1,
      },
    ],
  };

  return (
    <div className="container-fluid py-4">
      {/* Header Mejorado */}
      <div className="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h1 className="h2 mb-1">Panel de Analítica Avanzada</h1>
          <p className="text-muted">Análisis detallado de rendimiento y métricas financieras</p>
        </div>
        <div className="d-flex gap-2">
          <select
            className="form-select form-select-sm w-auto"
            value={dateRange}
            onChange={(e) => setDateRange(e.target.value)}
          >
            <option value="all">Todo el período</option>
            <option value="month">Este mes</option>
            <option value="quarter">Este trimestre</option>
            <option value="year">Este año</option>
          </select>
          <button className="btn btn-primary d-flex align-items-center" onClick={handleExport}>
            <i className="fas fa-download me-2"></i>
            Exportar Excel
          </button>
        </div>
      </div>

      {/* Navegación por pestañas mejorada */}
      <div className="card border-0 shadow-sm mb-4">
        <div className="card-body py-2">
          <ul className="nav nav-pills">
            <li className="nav-item">
              <button
                className={`nav-link ${activeTab === 'overview' ? 'active' : ''}`}
                onClick={() => setActiveTab('overview')}
              >
                <i className="fas fa-chart-bar me-2"></i>
                Resumen General
              </button>
            </li>
            <li className="nav-item">
              <button
                className={`nav-link ${activeTab === 'clients' ? 'active' : ''}`}
                onClick={() => setActiveTab('clients')}
              >
                <i className="fas fa-users me-2"></i>
                Clientes
              </button>
            </li>
            <li className="nav-item">
              <button
                className={`nav-link ${activeTab === 'payments' ? 'active' : ''}`}
                onClick={() => setActiveTab('payments')}
              >
                <i className="fas fa-credit-card me-2"></i>
                Pagos
              </button>
            </li>
            <li className="nav-item">
              <button
                className={`nav-link ${activeTab === 'interest' ? 'active' : ''}`}
                onClick={() => setActiveTab('interest')}
              >
                <i className="fas fa-percentage me-2"></i>
                Análisis de Interés
              </button>
            </li>
          </ul>
        </div>
      </div>

      {activeTab === 'overview' && (
        <div className="row">
          {/* Tarjetas de métricas principales mejoradas */}
          <div className="col-xl-2 col-md-4 col-sm-6 mb-4">
            <div className="card border-0 shadow-sm h-100">
              <div className="card-body">
                <div className="d-flex justify-content-between align-items-start">
                  <div>
                    <h6 className="card-title text-muted mb-2">Total Clientes</h6>
                    <h3 className="mb-0">{totals.clients ?? 0}</h3>
                    <small className="text-success">
                      <i className="fas fa-arrow-up me-1"></i>
                      5.2% vs período anterior
                    </small>
                  </div>
                  <div className="bg-primary bg-opacity-10 p-2 rounded">
                    <i className="fas fa-users text-primary"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="col-xl-2 col-md-4 col-sm-6 mb-4">
            <div className="card border-0 shadow-sm h-100">
              <div className="card-body">
                <div className="d-flex justify-content-between align-items-start">
                  <div>
                    <h6 className="card-title text-muted mb-2">Total Préstamos</h6>
                    <h3 className="mb-0">{totals.loans ?? 0}</h3>
                    <small className="text-success">
                      <i className="fas fa-arrow-up me-1"></i>
                      8.7% vs período anterior
                    </small>
                  </div>
                  <div className="bg-success bg-opacity-10 p-2 rounded">
                    <i className="fas fa-file-contract text-success"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="col-xl-2 col-md-4 col-sm-6 mb-4">
            <div className="card border-0 shadow-sm h-100">
              <div className="card-body">
                <div className="d-flex justify-content-between align-items-start">
                  <div>
                    <h6 className="card-title text-muted mb-2">Pagos Vencidos</h6>
                    <h3 className="mb-0">{totals.overdue_payments ?? 0}</h3>
                    <small className="text-danger">
                      <i className="fas fa-arrow-down me-1"></i>
                      3.1% vs período anterior
                    </small>
                  </div>
                  <div className="bg-danger bg-opacity-10 p-2 rounded">
                    <i className="fas fa-exclamation-triangle text-danger"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="col-xl-2 col-md-4 col-sm-6 mb-4">
            <div className="card border-0 shadow-sm h-100">
              <div className="card-body">
                <div className="d-flex justify-content-between align-items-start">
                  <div>
                    <h6 className="card-title text-muted mb-2">Monto Total</h6>
                    <h3 className="mb-0">${Number(totals.total_amount || 0).toLocaleString()}</h3>
                    <small className="text-success">
                      <i className="fas fa-arrow-up me-1"></i>
                      12.5% vs período anterior
                    </small>
                  </div>
                  <div className="bg-info bg-opacity-10 p-2 rounded">
                    <i className="fas fa-dollar-sign text-info"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="col-xl-2 col-md-4 col-sm-6 mb-4">
            <div className="card border-0 shadow-sm h-100">
              <div className="card-body">
                <div className="d-flex justify-content-between align-items-start">
                  <div>
                    <h6 className="card-title text-muted mb-2">Interés Generado</h6>
                    <h3 className="mb-0">${Number(interesGeneradoSimple).toLocaleString()}</h3>
                    <small className="text-success">
                      <i className="fas fa-arrow-up me-1"></i>
                      15.3% vs período anterior
                    </small>
                  </div>
                  <div className="bg-warning bg-opacity-10 p-2 rounded">
                    <i className="fas fa-chart-line text-warning"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="col-xl-2 col-md-4 col-sm-6 mb-4">
            <div className="card border-0 shadow-sm h-100">
              <div className="card-body">
                <div className="d-flex justify-content-between align-items-start">
                  <div>
                    <h6 className="card-title text-muted mb-2">Rentabilidad</h6>
                    <h3 className="mb-0">{rentabilidadReal}%</h3>
                    <small className="text-success">
                      <i className="fas fa-arrow-up me-1"></i>
                      2.1% vs período anterior
                    </small>
                  </div>
                  <div className="bg-success bg-opacity-10 p-2 rounded">
                    <i className="fas fa-percentage text-success"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Gráficos de resumen */}
          <div className="col-lg-6 mb-4">
            <div className="card border-0 shadow-sm h-100">
              <div className="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 className="card-title mb-0">Estado de Préstamos</h5>
                <span className="badge bg-primary">{totals.loans || 0} total</span>
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
                            label: function (context) {
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
              <div className="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 className="card-title mb-0">Resumen Financiero</h5>
                <span className="badge bg-success">${Number(totals.total_amount || 0).toLocaleString()}</span>
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
                            callback: function (value) {
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
              <div className="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 className="card-title mb-0">Clientes Principales por Volumen</h5>
                <span className="badge bg-primary">{top_clients.length} clientes</span>
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
                    <i className="fas fa-users display-4 text-muted"></i>
                    <p className="mt-2 text-muted">No hay clientes destacados.</p>
                  </div>
                )}
              </div>
            </div>
          </div>

          <div className="col-12">
            <div className="card border-0 shadow-sm">
              <div className="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 className="card-title mb-0">Resumen de Intereses por Cliente</h5>
                <span className="badge bg-success">${Number(interesGeneradoSimple).toLocaleString()} total</span>
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
                              callback: function (value) {
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
                    <i className="fas fa-chart-pie display-4 text-muted"></i>
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
              <div className="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 className="card-title mb-0">Distribución de Frecuencia de Pagos</h5>
                <span className="badge bg-info">{payment_frequency_summary.length} frecuencias</span>
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
                                  label: function (context) {
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
                          <thead className="table-light">
                            <tr>
                              <th>Frecuencia</th>
                              <th className="text-end">Cantidad</th>
                              <th className="text-end">Porcentaje</th>
                              <th className="text-end">Monto Promedio</th>
                            </tr>
                          </thead>
                          <tbody>
                            {payment_frequency_summary.map((item, index) => {
                              const total = payment_frequency_summary.reduce((sum, curr) => sum + (curr.total || 0), 0);
                              const percentage = total > 0 ? Math.round((item.total / total) * 100) : 0;
                              return (
                                <tr key={index}>
                                  <td>
                                    <span className="badge bg-primary me-2">{index + 1}</span>
                                    {item.payment_frequency ? item.payment_frequency.charAt(0).toUpperCase() + item.payment_frequency.slice(1) : 'Desconocido'}
                                  </td>
                                  <td className="text-end fw-bold">{item.total || 0}</td>
                                  <td className="text-end">
                                    <span className="badge bg-secondary">{percentage}%</span>
                                  </td>
                                  <td className="text-end text-success">
                                    ${Number(item.average_amount || 0).toLocaleString()}
                                  </td>
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
                    <i className="fas fa-calendar-check display-4 text-muted"></i>
                    <p className="mt-2 text-muted">No hay información de frecuencia de pagos.</p>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {activeTab === 'interest' && (
        <div className="row">
          <div className="col-lg-6 mb-4">
            <div className="card border-0 shadow-sm h-100">
              <div className="card-header bg-white">
                <h5 className="card-title mb-0">Comparación de Intereses</h5>
              </div>
              <div className="card-body">
                <div style={{ height: '300px' }}>
                  <Bar
                    data={interestComparisonData}
                    options={{
                      ...chartOptions,
                      scales: {
                        y: {
                          beginAtZero: true,
                          ticks: {
                            callback: function (value) {
                              return '$' + value.toLocaleString();
                            }
                          }
                        }
                      }
                    }}
                  />
                </div>
                <div className="row text-center mt-3">
                  <div className="col-6">
                    <div className="border-end">
                      <h4 className="text-info">${Number(interesGeneradoSimple).toLocaleString()}</h4>
                      <small className="text-muted">Interés Simple</small>
                    </div>
                  </div>
                  <div className="col-6">
                    <div>
                      <h4 className="text-warning">${Number(totalGanadoInteres).toLocaleString()}</h4>
                      <small className="text-muted">Total con Interés</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="col-lg-6 mb-4">
            <div className="card border-0 shadow-sm h-100">
              <div className="card-header bg-white">
                <h5 className="card-title mb-0">Detalle de Cálculo de Interés</h5>
              </div>
              <div className="card-body">
                <div className="table-responsive">
                  <table className="table table-sm">
                    <thead>
                      <tr>
                        <th>Concepto</th>
                        <th className="text-end">Valor</th>
                        <th className="text-end">Fórmula</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>Capital Total</td>
                        <td className="text-end fw-bold">${Number(totals.total_amount || 0).toLocaleString()}</td>
                        <td className="text-end text-muted">∑ Montos Préstamos</td>
                      </tr>
                      <tr>
                        <td>Interés Simple</td>
                        <td className="text-end fw-bold text-info">${Number(interesGeneradoSimple).toLocaleString()}</td>
                        <td className="text-end text-muted">∑ (Monto × Tasa)</td>
                      </tr>
                      <tr>
                        <td>Interés Total</td>
                        <td className="text-end fw-bold text-warning">${Number(totalGanadoInteres).toLocaleString()}</td>
                        <td className="text-end text-muted">∑ (Monto × Tasa × Cuotas)</td>
                      </tr>
                      <tr>
                        <td>Rentabilidad</td>
                        <td className="text-end fw-bold text-success">{rentabilidadReal}%</td>
                        <td className="text-end text-muted">(Interés Total / Capital) × 100</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <div className="col-12">
            <div className="card border-0 shadow-sm">
              <div className="card-header bg-white">
                <h5 className="card-title mb-0">Desglose de Interés por Préstamo</h5>
              </div>
              <div className="card-body">
                <div className="table-responsive">
                  <table className="table table-hover">
                    <thead className="table-light">
                      <tr>
                        <th>Cliente</th>
                        <th>Monto</th>
                        <th>Tasa</th>
                        <th>Cuotas</th>
                        <th>Interés Simple</th>
                        <th>Interés Total</th>
                        <th>Total a Pagar</th>
                      </tr>
                    </thead>
                    <tbody>
                      {loans.slice(0, 10).map((loan, index) => {
                        const monto = parseFloat(loan.amount || 0);
                        const tasa = parseFloat(loan.interest_rate || 0) / 100;
                        const cuotas = parseInt(loan.schedulesCount?.length || 1);
                        const interesSimple = monto * tasa;
                        const interesTotal = monto * tasa * cuotas;
                        const totalPagar = monto + interesTotal;
                        console.log(loan, monto, tasa, cuotas, interesSimple, interesTotal, totalPagar);
                        return (
                          <tr key={loan.id}>
                            <td>{loan.client?.name || `Préstamo ${index + 1}`}</td>
                            <td>${monto.toLocaleString()}</td>
                            <td>{(tasa * 100).toFixed(1)}%</td>
                            <td>{cuotas}</td>
                            <td className="text-info">${interesSimple.toLocaleString()}</td>
                            <td className="text-warning fw-bold">${interesTotal.toLocaleString()}</td>
                            <td className="text-success fw-bold">${totalPagar.toLocaleString()}</td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Analytics;