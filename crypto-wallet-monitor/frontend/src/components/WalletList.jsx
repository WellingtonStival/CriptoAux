import WalletItem from "./WalletItem";

function WalletList({ wallets }) {
  return (
    <ul className="flex flex-col gap-3">
      {wallets.map(wallet => (
        <WalletItem key={wallet.id} wallet={wallet} />
      ))}
    </ul>
  );
}

export default WalletList;
