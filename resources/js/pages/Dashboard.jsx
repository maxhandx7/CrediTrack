import React, { useState, useEffect } from 'react'
import StatsCard from '../components/StatsCard'

const Dashboard = () => {
  const [stats, setStats] = useState({
    totalPrestado: 0,
    totalRecuperado: 0,
    interesGenerado: 0,
    clientesMorosos: 0,
    prestamosActivos: 0,
    rentabilidad: '0%'
  })

  useEffect(() => {
    // Simular datos de la API
    setStats({
      totalPrestado: 125000,
      totalRecuperado: 89000,
      interesGenerado: 15000,
      clientesMorosos: 3,
      prestamosActivos: 12,
      rentabilidad: '12.5%'
    })
  }, [])

  return (
    <div>
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h2 className="h3 mb-0"></h2>
        <button className="btn btn-primary">
          <i className="fas fa-plus me-2"></i>
          Nuevo Préstamo
        </button>
      </div>

      <div className="row g-4 mb-4">
        <div className="col-xl-3 col-md-6">
          <StatsCard
            title="Total Prestado"
            value={`$${stats.totalPrestado.toLocaleString()}`}
            icon="fa-hand-holding-usd"
            color="primary"
          />
        </div>
        <div className="col-xl-3 col-md-6">
          <StatsCard
            title="Total Recuperado"
            value={`$${stats.totalRecuperado.toLocaleString()}`}
            icon="fa-money-bill-wave"
            color="success"
          />
        </div>
        <div className="col-xl-3 col-md-6">
          <StatsCard
            title="Interés Generado"
            value={`$${stats.interesGenerado.toLocaleString()}`}
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
      </div>

      <div className="row">
        <div className="col-lg-8">
          <div className="card border-0 shadow-sm">
            <div className="card-header bg-white">
              <h5 className="mb-0">Préstamos Recientes</h5>
            </div>
            <div className="card-body">
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
                    <tr>
                      <td>Juan Pérez</td>
                      <td>$10,000</td>
                      <td>15/06/2024</td>
                      <td><span className="badge bg-success">Al día</span></td>
                    </tr>
                    <tr>
                      <td>María García</td>
                      <td>$15,000</td>
                      <td>10/06/2024</td>
                      <td><span className="badge bg-danger">Vencido</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        
        <div className="col-lg-4">
          <div className="card border-0 shadow-sm">
            <div className="card-header bg-white">
              <h5 className="mb-0">Recordatorios</h5>
            </div>
            <div className="card-body">
              <div className="list-group list-group-flush">
                <div className="list-group-item px-0">
                  <small className="text-muted">Hoy</small>
                  <p className="mb-1">Recordar pago a Carlos López</p>
                </div>
                <div className="list-group-item px-0">
                  <small className="text-muted">Mañana</small>
                  <p className="mb-1">Revisión de préstamos vencidos</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default Dashboard