import React from 'react'

const StatsCard = ({ title, value, icon, color, subtitle }) => {
  return (
    <div className="card border-0 shadow-sm h-100">
      <div className="card-body">
        <div className="row align-items-center">
          <div className="col">
            <h6 className="card-title text-muted mb-2">{title}</h6>
            <h3 className="fw-bold text-dark">{value}</h3>
            {subtitle && <small className="text-muted">{subtitle}</small>}
          </div>
          <div className="col-auto">
            <div className={`icon-shape icon-lg bg-${color} text-white rounded-circle`}>
              <i className={`fas ${icon}`}></i>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default StatsCard