import WalletItem from "./WalletItem";

function WalletList({ wallets }) {
  return (
    <ul>
      {wallets.map(wallet => (
        <WalletItem key={wallet.id} wallet={wallet} />
      ))}
    </ul>
  );
}

export default WalletList;