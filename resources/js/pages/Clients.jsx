import React, { useState, useEffect } from 'react'
import { useAuth } from '../contexts/AuthContext'
import api from '../service/api'

const Clients = () => {
  const [clients, setClients] = useState([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [showModal, setShowModal] = useState(false)
  const [editingClient, setEditingClient] = useState(null)

  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    document: '',
    address: '',
    notes: ''
  })

  const { token } = useAuth()

  // 🔹 Cargar clientes desde el backend
  const fetchClients = async () => {
    try {
      setLoading(true)
      const response = await api.get('/clients', {
        headers: { Authorization: `Bearer ${token}` },
      })
      setClients(response.data)
    } catch (err) {
      setError('Error al cargar los clientes')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchClients()
  }, [])

  // 🔹 Abrir modal (nuevo o editar)
  const openModal = (client = null) => {
    if (client) {
      setEditingClient(client)
      setFormData({
        name: client.name,
        email: client.email || '',
        phone: client.phone || '',
        document: client.document,
        address: client.address || '',
        notes: client.notes || ''
      })
    } else {
      setEditingClient(null)
      setFormData({
        name: '',
        email: '',
        phone: '',
        document: '',
        address: '',
        notes: ''
      })
    }
    setShowModal(true)
    setError('')
    setSuccess('')
  }

  // 🔹 Crear o actualizar cliente
  const handleSubmit = async (e) => {
    e.preventDefault()
    setLoading(true)
    setError('')
    setSuccess('')

    try {
      if (editingClient) {
        await api.put(`/clients/${editingClient.id}`, formData, {
          headers: { Authorization: `Bearer ${token}` },
        })
        setSuccess('Cliente actualizado correctamente')
      } else {
        await api.post('/clients', formData, {
          headers: { Authorization: `Bearer ${token}` },
        })
        setSuccess('Cliente creado correctamente')
      }
      setShowModal(false)
      fetchClients()
    } catch (err) {
      const msg =
        err.response?.data?.message ||
        'Ocurrió un error al guardar el cliente'
      setError(msg)
    } finally {
      setLoading(false)
    }
  }

  // 🔹 Eliminar cliente
  const handleDelete = async (id) => {
    if (!window.confirm('¿Seguro que deseas eliminar este cliente?')) return

    try {
      await api.delete(`/clients/${id}`, {
        headers: { Authorization: `Bearer ${token}` },
      })
      setSuccess('Cliente eliminado correctamente')
      fetchClients()
    } catch (err) {
      setError('Error al eliminar el cliente')
    }
  }

  return (
    <div>
      <div className="d-flex justify-content-between align-items-center mb-4">
        <h2 className="h3 mb-0">Gestión de Clientes</h2>
        <button className="btn btn-primary" onClick={() => openModal()}>
          <i className="fas fa-plus me-2"></i> Nuevo Cliente
        </button>
      </div>

      {error && <div className="alert alert-danger">{error}</div>}
      {success && <div className="alert alert-success">{success}</div>}

      <div className="card border-0 shadow-sm">
        <div className="card-body">
          {loading ? (
            <div className="text-center py-4">
              <div className="spinner-border text-primary"></div>
              <p className="mt-2">Cargando clientes...</p>
            </div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover align-middle">
                <thead className="table-light">
                  <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Documento</th>
                    <th>Dirección</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  {clients.length > 0 ? (
                    clients.map((client) => (
                      <tr key={client.id}>
                        <td>{client.name}</td>
                        <td>{client.email || '—'}</td>
                        <td>{client.phone || '—'}</td>
                        <td>{client.document}</td>
                        <td>{client.address || '—'}</td>
                        <td >
                          <button
                            className="btn btn-sm btn-outline-primary me-2"
                            onClick={() => openModal(client)}
                          >
                            <i className="fas fa-edit"></i>
                          </button>
                          <button
                            className="btn btn-sm btn-outline-danger"
                            onClick={() => handleDelete(client.id)}
                          >
                            <i className="fas fa-trash"></i>
                          </button>
                        </td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td colSpan="6" className="text-center py-4 text-muted">
                        No hay clientes registrados
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {/* Modal de creación/edición */}
      {showModal && (
        <div className="modal show d-block" tabIndex="-1">
          <div className="modal-dialog">
            <div className="modal-content shadow-lg">
              <div className="modal-header">
                <h5 className="modal-title">
                  {editingClient ? 'Editar Cliente' : 'Nuevo Cliente'}
                </h5>
                <button
                  type="button"
                  className="btn-close"
                  onClick={() => setShowModal(false)}
                ></button>
              </div>

              <form onSubmit={handleSubmit}>
                <div className="modal-body">
                  <div className="mb-3">
                    <label className="form-label">Nombre completo *</label>
                    <input
                      type="text"
                      className="form-control"
                      value={formData.name}
                      onChange={(e) =>
                        setFormData({ ...formData, name: e.target.value })
                      }
                      required
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Documento *</label>
                    <input
                      type="text"
                      className="form-control"
                      value={formData.document}
                      onChange={(e) =>
                        setFormData({ ...formData, document: e.target.value })
                      }
                      required
                      disabled={!!editingClient}
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Email</label>
                    <input
                      type="email"
                      className="form-control"
                      value={formData.email}
                      onChange={(e) =>
                        setFormData({ ...formData, email: e.target.value })
                      }
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Teléfono</label>
                    <input
                      type="text"
                      className="form-control"
                      value={formData.phone}
                      onChange={(e) =>
                        setFormData({ ...formData, phone: e.target.value })
                      }
                    />
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Dirección</label>
                    <textarea
                      className="form-control"
                      value={formData.address}
                      onChange={(e) =>
                        setFormData({ ...formData, address: e.target.value })
                      }
                    ></textarea>
                  </div>
                  <div className="mb-3">
                    <label className="form-label">Notas</label>
                    <textarea
                      className="form-control"
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
                  <button
                    type="submit"
                    className="btn btn-primary"
                    disabled={loading}
                  >
                    {loading
                      ? 'Guardando...'
                      : editingClient
                      ? 'Actualizar Cliente'
                      : 'Guardar Cliente'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export default Clients
