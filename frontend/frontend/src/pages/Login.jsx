import { useState } from 'react';
import api from '../services/api';

function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');

    try {
      const response = await api.post('/login', {
        email,
        password,
      });

      localStorage.setItem('token', response.data.token);

      // redireciona para a home protegida
      window.location.href = '/';
    } catch (err) {
       setError('Login inválido');
    }
  }

  return (
    <div>
      <h2>Login</h2>

      {error && <p style={{ color: 'red' }}>{error}</p>}

      <form onSubmit={handleSubmit}>
        <input
          type="email"
          placeholder="Email"
          value={email}
          onChange={e => setEmail(e.target.value)}
          required
        />

        <br />

        <input
          type="password"
          placeholder="Senha"
          value={password}
          onChange={e => setPassword(e.target.value)}
          required
        />

        <br />

        <button type="submit">Entrar</button>
      </form>
    </div>
  );
}

export default Login;