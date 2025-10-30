import React from 'react'
import { Link, useLocation } from 'react-router-dom'

const Sidebar = () => {
  const location = useLocation()

  const menuItems = [
    { path: '/', icon: 'fas fa-tachometer-alt', label: 'Dashboard' },
    { path: '/clients', icon: 'fas fa-users', label: 'Clientes' },
    { path: '/loans', icon: 'fas fa-hand-holding-usd', label: 'Préstamos' },
    { path: '/payments', icon: 'fas fa-money-bill-wave', label: 'Pagos' },
    { path: '/analytics', icon: 'fas fa-chart-bar', label: 'Analítica' },
    { path: '/calendar', icon: 'fas fa-calendar-alt', label: 'Calendario' },
  ]

  return (
    <div className="col-lg-2 col-md-3 bg-dark text-white vh-100 position-fixed">
      <div className="sidebar-sticky pt-3">
        <div className="text-center mb-4">
          <h4 className="text-warning">
            <i className="fas fa-chart-line me-2"></i>
            CrediTrack
          </h4>
        </div>
        
        <ul className="nav flex-column">
          {menuItems.map((item) => (
            <li key={item.path} className="nav-item">
              <Link
                to={item.path}
                className={`nav-link text-white ${
                  location.pathname === item.path ? 'bg-primary rounded' : ''
                }`}
              >
                <i className={`${item.icon} me-2`}></i>
                {item.label}
              </Link>
            </li>
          ))}
        </ul>
      </div>
    </div>
  )
}

export default Sidebar